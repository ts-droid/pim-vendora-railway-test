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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function translateRequestStream(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        set_time_limit(0);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $string = $request->string;
        $sourceLang = $request->source_lang;
        $targetLang = $request->target_lang;

        $languages = (new LanguageController())->getAllLanguages();
        foreach ($languages as $language) {
            if ($language->language_code == $sourceLang) {
                $sourceLang = $language->title;
            }
            if ($language->language_code == $targetLang) {
                $targetLang = $language->title;
            }
        }

        $excludes = [];
        if ($request->has('excludes')) {
            $excludes = $request->excludes ?: [];
        }

        $excludes = array_merge($excludes, TranslateExcludeService::getAll());
        $excludes = array_map('trim', $excludes);
        $excludes = array_filter($excludes);
        $excludes = array_unique($excludes);

        $promptController = new PromptController();
        $prompt = $promptController->getBySystemCode('translation_ai_prompt');

        $inputs = [
            'string' => $string,
            'sourceLang' => $sourceLang,
            'targetLang' => $targetLang,
            'excludes' => implode(PHP_EOL, $excludes)
        ];

        $prompt->system = $promptController->replaceInputs($prompt->system, $inputs);
        $prompt->message = $promptController->replaceInputs($prompt->message, $inputs);

        $aiService = new AIService('gpt-5-mini-2025-08-07');
        $streamData = $aiService->streamChatCompletion($prompt->system, $prompt->message);

        $headers = [];
        foreach ($streamData['headers'] as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        $streamData['headers'] = $headers;

        $response = new StreamedResponse(function () use ($streamData) {
            $ch = curl_init($streamData['url']);

            curl_setopt($ch, CURLOPT_HTTPHEADER, $streamData['headers']);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($streamData['body']));
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                echo $data;
                if (ob_get_length() !== false) {
                    ob_flush();
                    flush();
                }

                return strlen($data);
            });

            curl_exec($ch);

            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
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

        $translations = $this->translate($strings, $sourceLang, $targetLang, $isHTML, $excludes, $engine);

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
    public function translate(array $strings, string $sourceLang, string $targetLang, bool $isHTML = false, array $excludes = [], ?string $engine = null): array
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        // Normalize engine
        if (!in_array($engine, ['deepl', 'openai'])) {
            $engine = 'deepl'; // This is the default engine
        }

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

        // Replace excludes with placeholders
        $excludeMap = [];
        if (count($excludes) > 0) {
            usort($excludes, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
            $tokenPrefix = '__DNT_' . bin2hex(random_bytes(4)) . '_';
            $tokenIndex = 0;

            foreach ($excludes as $term) {
                if ($term === '') {
                    continue;
                }

                $token = $tokenPrefix . $tokenIndex . '__';
                $excludeMap[$token] = $term;
                $tokenIndex++;

                for ($j = 0;$j < count($strings);$j++) {
                    $strings[$j] = str_replace($term, $token, $strings[$j]);
                }
            }
        }

        // Translate all the strings
        $translations = [];
        foreach ($strings as $string) {
            if ($engine === 'deepl') {
                $translations[] = $this->translateDeepl($string, $sourceLang, $targetLang);
            } elseif ($engine === 'openai') {
                $translations[] = $this->translateOpenAI($string, $sourceLang, $targetLang);
            }
        }


        for ($j = 0;$j < count($translations);$j++) {
            // Remove <dnt> tags from the text
            // $original = $originalStrings[$j] ?? '';
            // $translations[$j] = $this->stripDntAndFixHtmlSpacing($translations[$j], $original);

            if (count($excludeMap) > 0) {
                $translations[$j] = str_replace(
                    array_keys($excludeMap),
                    array_values($excludeMap),
                    $translations[$j]
                );
            }

            // Fix HTML entities
            $translations[$j] = html_entity_decode($translations[$j]);
        }

        return $translations;
    }

    private function translateDeepl(string $string, string $sourceLang, string $targetLang): string
    {
        $options = [
            'tag_handling' => 'html',
            'tag_handling_version' => 'v2',
            'ignore_tags' => ['dnt'],
            'non_splitting_tags' => ['dnt'],
            'preserve_formatting' => true,
        ];

        try {
            $string = preg_replace( '/\r|\n/', '', $string);
            $string = preg_replace('/<br\s*>/i', '<br/>', $string);

            return (string) $this->translator->translateText(
                $string,
                $this->formatLanguageCode($sourceLang),
                $this->formatLanguageCode($targetLang, true),
                $options
            );
        } catch (Exception $e) {
            return '';
        }
    }

    private function translateOpenAI(string $string, string $sourceLang, string $targetLang): string
    {
        try {
            $languages = (new LanguageController())->getAllLanguages();
            foreach ($languages as $language) {
                if ($language->language_code == $sourceLang) {
                    $sourceLang = $language->title;
                }
                if ($language->language_code == $targetLang) {
                    $targetLang = $language->title;
                }
            }

            $promptController = new PromptController();
            $prompt = $promptController->getBySystemCode('translation_ai_prompt');

            return $promptController->execute(
                $prompt->id,
                ['string' => $string, 'sourceLang' => $sourceLang, 'targetLang' => $targetLang],
                '',
                'gpt-5-mini-2025-08-07',
            );
        } catch (Exception $e) {
            return '';
        }
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

        $html = preg_replace('/(\s)<dnt>/u', '&nbsp;<dnt>', $html);
        $html = preg_replace('/<\/dnt>(\s)/u', '</dnt>&nbsp;', $html);

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

    private function stripDntAndFixHtmlSpacing(string $s, string $original = ''): string
    {
        if ($original !== '') {
            $s = $this->restoreWhitespaceAroundDnt($s, $original);
        }

        // 1) Drop the <dnt> tags
        $s = preg_replace('/<\/?dnt>/iu', '', $s);

        // 2) Convert our &nbsp; sentinels back to normal spaces
        //    (use a conservative replace so real non-breaking spaces elsewhere remain)
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

        // 3) Fix “</tag>word” and “word<tag>” glue where both sides are letters/digits
        //    a) ...X</b>Y... → ...X</b> Y...
        $s = preg_replace('/(\pL|\pN)(<\/[^>]+>)(\pL|\pN)/u', '$1$2 $3', $s);
        //    b) ...X(<tag> or </tag>)Y... → ...X $2 Y...  [rare but safe]
        $s = preg_replace('/(\pL|\pN)(<[^>]+>)(\pL|\pN)/u', '$1 $2 $3', $s);

        // 4) Collapse excessive whitespace (not newlines)
        $s = preg_replace('/[^\S\r\n]+/u', ' ', $s);

        return $s;
    }

    private function restoreWhitespaceAroundDnt(string $translated, string $original): string
    {
        $cursor = 0;
        $originalCursor = 0;
        $originalLength = mb_strlen($original, 'UTF-8');

        while (($tagStart = strpos($translated, '<dnt>', $cursor)) !== false) {
            $tagEnd = strpos($translated, '</dnt>', $tagStart);
            if ($tagEnd === false) {
                break;
            }

            $termStart = $tagStart + 5;
            $term = substr($translated, $termStart, $tagEnd - $termStart);

            if ($term === '') {
                $cursor = $tagEnd + 6;
                continue;
            }

            $origPos = mb_strpos($original, $term, $originalCursor, 'UTF-8');
            if ($origPos === false) {
                $originalCursor = 0;
                $origPos = mb_strpos($original, $term, $originalCursor, 'UTF-8');
                if ($origPos === false) {
                    $cursor = $tagEnd + 6;
                    continue;
                }
            }

            $termLength = mb_strlen($term, 'UTF-8');
            $originalCursor = $origPos + $termLength;

            $hasSpaceBefore = $origPos > 0 && preg_match('/\s/u', mb_substr($original, $origPos - 1, 1, 'UTF-8'));
            $hasSpaceAfter = $originalCursor < $originalLength && preg_match('/\s/u', mb_substr($original, $originalCursor, 1, 'UTF-8'));

            if ($hasSpaceBefore) {
                if ($tagStart === 0 || !preg_match('/\s/u', substr($translated, $tagStart - 1, 1))) {
                    $translated = substr_replace($translated, ' ', $tagStart, 0);
                    $tagStart += 1;
                    $tagEnd += 1;
                    $termStart += 1;
                }
            }

            if ($hasSpaceAfter) {
                $afterPos = $tagEnd + 6;
                if ($afterPos >= strlen($translated) || !preg_match('/\s/u', substr($translated, $afterPos, 1))) {
                    $translated = substr_replace($translated, ' ', $afterPos, 0);
                    $tagEnd += 1;
                }
            }

            $cursor = $tagEnd + 6;
        }

        return $translated;
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
