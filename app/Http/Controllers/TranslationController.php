<?php

namespace App\Http\Controllers;

use App\Models\TranslationService;
use App\Services\AI\AIService;
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
        $this->translator = new Translator(config('services.deepl.api_key'));
    }

    public function getEngines() {
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

        $excludes = array_merge($excludes, $globalExcludes);

        $excludes = array_filter($excludes);

        // Wrap excludes with <nt> tags
        for ($i = 0;$i < count($excludes);$i++) {
            for ($j = 0;$j < count($strings);$j++) {
                $strings[$j] = str_replace($excludes[$i], '<nt>' . $excludes[$i] . '</nt>', $strings[$j]);
            }
        }


        // Translate all the strings
        $options = [
            'tag_handling' => 'xml',
            'ignore_tags' => ['nt'],
            'preserve_formatting' => true,
        ];

        $translations = [];

        foreach ($strings as $string) {
			$string = preg_replace( '/\r|\n/', '', $string);

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


        // Remove <nt> tags from the text
        for ($j = 0;$j < count($translations);$j++) {
            $translations[$j] = $this->stripNtTagsSmart($translations[$j]);
        }


        return $translations;
    }

    private function stripNtTagsSmart(string $s): string
    {
        // Case 1: word<nt>TERM</nt>word  -> word TERM word
        $s = preg_replace('/(\pL|\pN)<nt>(.*?)<\/nt>(\pL|\pN)/u', '$1 $2 $3', $s);

        // Case 2: word<nt>TERM</nt> -> word TERM
        $s = preg_replace('/(\pL|\pN)<nt>(.*?)<\/nt>/u', '$1 $2', $s);

        // Case 3: <nt>TERM</nt>word -> TERM word
        $s = preg_replace('/<nt>(.*?)<\/nt>(\pL|\pN)/u', '$1 $2', $s);

        // Case 4: general fallback (boundaries/punctuation): just drop the tags
        $s = preg_replace('/<nt>(.*?)<\/nt>/u', '$1', $s);

        // Normalize runs of whitespace to a single space (but keep newlines if any)
        $s = preg_replace('/[^\S\r\n]+/u', ' ', $s);
        return $s;
    }

    public function translateAI(array $strings, string $sourceLang, string $targetLang, array $excludes = [], string $model = ''): array
    {
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
