<?php

namespace App\Http\Controllers;

use App\Models\TranslationService;
use App\Services\AI\AIService;
use App\Services\TranslateExcludeService;
use App\Services\TranslationServiceManager;
use DeepL\Translator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TranslationController extends Controller
{
    /**
     * @var Translator
     */
    private Translator $translator;

    function __construct()
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $this->translator = new Translator(config('services.deepl.api_key'));
    }

    public function getEngines() {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $manager = new TranslationServiceManager();
        $services = $manager->getAllServices();

        return ApiResponseController::success($services->toArray());
    }

    /**
     * API call to translate
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function translateRequest(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $validator = Validator::make($request->all(), [
            'strings' => 'required',
            'source_lang' => 'required|string',
            'target_lang' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $strings = $request->strings;
        $sourceLang = $request->source_lang;
        $targetLang = $request->target_lang;
        $isHTML = (bool) ($request->is_html ?? 0);
        $engine = $request->engine ?? null;

        $excludes = [];
        if ($request->has('excludes')) {
            $excludes = $request->excludes ?: [];
        }

        $excludes = array_merge($excludes, TranslateExcludeService::getAll());
        $excludes = array_map('trim', $excludes);
        $excludes = array_filter($excludes);
        $excludes = array_unique($excludes);


        if (!is_array($strings)) {
            $strings = [$strings];
        }

        if (!$engine || $engine == 'deepl') {
            $translations = $this->translate($strings, $sourceLang, $targetLang, $isHTML, $excludes);
        }
        else {
            $translationService = TranslationService::where('name', $engine)->first();
            $defaultModel = (string) ($translationService->default_model ?? '');

            $translations = $this->translateAI($strings, $sourceLang, $targetLang, $excludes, $defaultModel);
        }


        // Replace language URLs
        $languageController = new LanguageController();
        foreach ($languageController->getAllLanguages() as $language) {
            for ($i = 0;$i < count($translations);$i++) {
                $translations[$i] = str_replace('.com/' . $language->language_code . '/', '.com/' . $targetLang . '/', $translations[$i]);
                $translations[$i] = str_replace('.se/' . $language->language_code . '/', '.se/' . $targetLang . '/', $translations[$i]);
                $translations[$i] = str_replace('.net/' . $language->language_code . '/', '.net/' . $targetLang . '/', $translations[$i]);
            }
        }

        return ApiResponseController::success($translations);
    }

    /**
     * Returns an array with the translated strings
     *
     * @param array $strings
     * @param string $sourceLang
     * @param string $targetLang
     * @param bool $isHTML
     * @return array
     */
    public function translate(array $strings, string $sourceLang, string $targetLang, bool $isHTML = false, array $excludes = []): array
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        /*Log::channel('deepl')->info(json_encode([
            'strings' => $strings,
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
            'isHTML' => $isHTML,
            'excludes' => $excludes
        ]));*/

        // Merge excludes with global excludes
        $globalExcludes = ConfigController::getConfig('translation_excludes');
        $globalExcludes = preg_split("/\r\n|\n|\r/", $globalExcludes);

        $globalExcludes = array_merge($globalExcludes, TranslateExcludeService::getAll());

        $variables = [];
        $urls = [];
        for ($j = 0;$j < count($strings);$j++) {
            preg_match_all('/%[a-zA-Z0-9_]+%/', $strings[$j], $matches);
            $variables = array_merge($variables, $matches[0]);

            preg_match_all('/{[a-zA-Z0-9_]+}/', $strings[$j], $matches);
            $variables = array_merge($variables, $matches[0]);

            preg_match_all('#https?://[^\s<]+#i', $strings[$j], $matches);
            $urls = array_merge($urls, $matches[0]);
        }

        $excludes = array_merge($excludes, $globalExcludes, $variables, $urls);
        $excludes = array_unique($excludes);
        $excludes = array_filter($excludes);

        // Wrap excludes with <dnt> tags
        for ($j = 0;$j < count($strings);$j++) {
            $strings[$j] = $this->wrapExcludesWithDntHtml($strings[$j], $excludes);
        }


        // Translate all the strings
        $options = [
            'tag_handling' => 'html',
            'ignore_tags' => ['dnt'],
            'non_splitting_tags' => ['dnt'],
            'preserve_formatting' => true,
        ];

        $translations = [];

        foreach ($strings as $string) {
			$string = preg_replace( '/\r|\n/', '', $string);
			$string = preg_replace('/<br\s*>/i', '<br/>', $string);

            try {
                $translation = (string) $this->translator->translateText(
                    $string,
                    $this->formatLanguageCode($sourceLang),
                    $this->formatLanguageCode($targetLang, true),
                    $options
                );

                $translations[] = $translation;
            } catch (Exception $e) {
                $translations[] = '';
            }
        }


        for ($j = 0;$j < count($translations);$j++) {
            // Remove <dnt> tags from the text
            $translations[$j] = $this->stripDntAndFixHtmlSpacing($translations[$j]);

            // Fix HTML entities
            $translations[$j] = html_entity_decode($translations[$j]);
        }

        return $translations;
    }

    private function wrapExcludesWithDntHtml(string $html, array $excludes): string
    {
        // longest-first to avoid partial matches inside larger terms
        usort($excludes, fn($a,$b) => mb_strlen($b) <=> mb_strlen($a));

        $dntRanges = [];

        foreach ($excludes as $term) {
            if ($term === '') continue;

            $pattern = '/' . preg_quote($term, '/') . '/u';
            if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $shift = 0;

            foreach ($matches[0] as [$match, $offset]) {
                $offset += $shift;

                if ($this->isOffsetInsideDnt($html, $offset)) {
                    continue;
                }

                $replacement = '<dnt>' . $match . '</dnt>';
                $html = substr_replace($html, $replacement, $offset, strlen($match));

                $delta = strlen($replacement) - strlen($match);
                $shift += $delta;
                $dntRanges[] = [$offset, $offset + strlen($replacement)];
            }

            // keep ranges ordered for the helper
            usort($dntRanges, fn ($a, $b) => $a[0] <=> $b[0]);
        }

        return $html;
    }

    private function isOffsetInsideDnt(string $html, int $offset): bool
    {
        if ($offset <= 0) {
            return false;
        }

        $before = substr($html, 0, $offset);
        $opened = substr_count($before, '<dnt>');
        if ($opened === 0) {
            return false;
        }

        $closed = substr_count($before, '</dnt>');

        return $opened > $closed;
    }

    private function stripDntAndFixHtmlSpacing(string $s): string
    {
        $s = preg_replace('/<\/?dnt>/iu', '', $s);

        $s = str_replace(
            [
                "\u{00A0}", // regular NBSP
                "\u{202F}", // narrow NBSP, just in case
                "&nbsp;",
                "&#160;",
                "&#xA0;",
                "&#xa0;",
            ],
            ' ',
            $s
        );

        return $s;
    }

    public function translateAI(array $strings, string $sourceLang, string $targetLang, array $excludes = [], string $model = ''): array
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        // Merge excludes with global excludes
        $globalExcludes = ConfigController::getConfig('translation_excludes');
        $globalExcludes = preg_split("/\r\n|\n|\r/", $globalExcludes);

        $excludes = array_merge($excludes, $globalExcludes);

        $excludes = array_filter($excludes);

        // Replace excludes with placeholders
        for ($i = 0;$i < count($excludes);$i++) {
            for ($j = 0;$j < count($strings);$j++) {
                $strings[$j] = str_replace($excludes[$i], '[1010_' . $i . ']', $strings[$j]);
            }
        }

        // Translate all the strings
        $AIService = new AIService($model);

        $translations = [];

        foreach ($strings as $string) {
            $translations[] = $AIService->translate($string, $sourceLang, $targetLang);
        }

        // Replace placeholders with excludes
        for ($i = 0;$i < count($excludes);$i++) {
            for ($j = 0;$j < count($translations);$j++) {
                $translations[$j] = str_replace('[1010_' . $i . ']', $excludes[$i], $translations[$j]);
            }
        }

        return $translations;
    }

    /**
     * Formats the language code to work with DeepL API
     *
     * @param string $languageCode
     * @param bool $isTarget
     * @return string
     */
    private function formatLanguageCode(string $languageCode, bool $isTarget = false): string
    {
        switch ($languageCode) {
            case 'en':
                return ($isTarget ? 'en-US' : 'en');

            case 'no':
                return 'nb';

            default:
                return $languageCode;
        }
    }
}
