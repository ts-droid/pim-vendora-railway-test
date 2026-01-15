<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Services\AI\AIService;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function execute(int $promptID, array $inputs = [], string $customInstructions = '', string $model = '', string $imageURL = ''): string
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        // Load main prompt
        $prompt = $this->get($promptID);

        // Load and merge parent prompt if exists
        // TODO: Handle multiple levels of parents
        if ($prompt->parent) {
            $parentPrompt = $this->getBySystemCode($prompt->parent);
            if ($parentPrompt) {

                $prompt->system = $parentPrompt->system . PHP_EOL . PHP_EOL . $prompt->system;
                $prompt->message = $parentPrompt->message . PHP_EOL . PHP_EOL . $prompt->message;
            }
        }

        // Replace variables
        $prompt->system = $this->replaceInputs($prompt->system, $inputs);
        $prompt->message = $this->replaceInputs($prompt->message, $inputs);

        $system = $prompt->system;

        if ($customInstructions) {
            $system .= PHP_EOL . PHP_EOL . $customInstructions;
        }

        $AIService = new AIService($model);
        $response = $AIService->chatCompletion($system, $prompt->message, null, $imageURL);

        return $response;
    }

    public function getGroup(string $group)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        return Prompt::where('group', $group)
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function getBySystemCode(string $systemCode): Prompt|null
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return Prompt::where('system_code', $systemCode)->first();
    }

    public function get(int $promptID): Prompt|null
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return Prompt::where('id', $promptID)->first();
    }

    public function store(
        int $promptID,
        string $systemCode,
        string $group,
        string $name,
        string $system,
        string $message,
        array $inputs = [],
        string $parent = ''
    ): Prompt
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $prompt = Prompt::where('id', $promptID)->first();

        if (!$prompt) {
            $prompt = new Prompt();
        }

        $prompt->system_code = $systemCode;
        $prompt->group = $group;
        $prompt->name = $name;
        $prompt->system = $system;
        $prompt->message = $message;
        $prompt->inputs = json_encode(array_filter($inputs));
        $prompt->parent = $parent;
        $prompt->save();

        return $prompt;
    }

    public function replaceInputs(string $string, array $inputs): string
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        foreach ($inputs as $key => $value) {
            $string = str_replace('{' . $key . '}', $value, $string);
        }

        return $string;
    }
}
