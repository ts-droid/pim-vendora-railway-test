<?php

namespace App\Http\Controllers;

use DeepL\Translator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TranslationController extends Controller
{
    const API_KEY = '5cedf04c-eeb6-ae4b-df39-59bec172476b';

    /**
     * @var Translator
     */
    private Translator $translator;

    function __construct()
    {
        $this->translator = new Translator(self::API_KEY);
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

        if (!is_array($strings)) {
            $strings = [$strings];
        }

        $translations = $this->translate($strings, $sourceLang, $targetLang, $isHTML);

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
    public function translate(array $strings, string $sourceLang, string $targetLang, bool $isHTML = false): array
    {
        $options = [];

        if ($isHTML) {
            $options['tag_handling'] = 'html';

			foreach ($strings as &$string) {
				$string = str_replace(array("\r", "\n"), '', $string);
			}
        }

        $translations = [];

        foreach ($strings as $string) {
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
