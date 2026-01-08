<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LanguageApiController extends Controller
{
    /**
     * Returns all supported languages
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $languageController = new LanguageController();

        $languages = $languageController->getAllLanguages();

        return ApiResponseController::success($languages->toArray());
    }

    /**
     * Returns all active languages
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActive(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $languageController = new LanguageController();

        $languages = $languageController->getActiveLanguages();

        return ApiResponseController::success($languages->toArray());
    }

    /**
     * Returns a language by language code
     *
     * @param Request $request
     * @param string $languageCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByCode(Request $request, string $languageCode)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $languageController = new LanguageController();

        $language = $languageController->getLanguageByCode($languageCode);

        $language = $language ? $language->toArray() : [];

        return ApiResponseController::success($language);
    }

    /**
     * Activate a language
     *
     * @param Request $request
     * @param string $languageCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateLanguage(Request $request, string $languageCode)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $languageController = new LanguageController();

        $language = $languageController->getLanguageByCode($languageCode);

        if (!$language) {
            return ApiResponseController::error('Language code not found.');
        }

        $language = $languageController->activateLanguage($language);

        return ApiResponseController::success($language->toArray());
    }

    /**
     * Deactivate a language
     *
     * @param Request $request
     * @param string $languageCode
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivateLanguage(Request $request, string $languageCode)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $languageController = new LanguageController();

        $language = $languageController->getLanguageByCode($languageCode);

        if (!$language) {
            return ApiResponseController::error('Language code not found.');
        }

        $language = $languageController->deactivateLanguage($language);

        return ApiResponseController::success($language->toArray());
    }

    /**
     * Create a new language
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createLanguage(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $validator = Validator::make($request->all(), [
            'language_code' => 'required|string',
            'title' => 'required|string',
            'title_local' => 'required|string',
            'default_currency' => 'required|string',
            'country_code' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $languageController = new LanguageController();

        $language = $languageController->createLanguage(
            $request->language_code,
            $request->title,
            $request->title_local,
            $request->default_currency,
            $request->country_code,
        );

        if (!$language) {
            return ApiResponseController::error('Failed to create language.');
        }

        return ApiResponseController::success($language->toArray());
    }
}
