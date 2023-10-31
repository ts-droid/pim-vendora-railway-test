<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;

class PromptController extends Controller
{
    public function execute(int $promptID, array $inputs = [], string $customInstructions = ''): string
    {
        $prompt = $this->get($promptID);

        $prompt->system = $this->replaceInputs($prompt->system, $inputs);
        $prompt->message = $this->replaceInputs($prompt->message, $inputs);

        $system = $prompt->system;

        if ($customInstructions) {
            $system .= PHP_EOL . PHP_EOL . $customInstructions;
        }

        $openAIController = new OpenAIController();
        $response = $openAIController->chatCompletion($system, $prompt->message);

        return $response;
    }

    public function getGroup(string $group)
    {
        return Prompt::where('group', $group)
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->get();
    }

    public function getBySystemCode(string $systemCode): Prompt|null
    {
        return Prompt::where('system_code', $systemCode)->first();
    }

    public function get(int $promptID): Prompt|null
    {
        return Prompt::where('id', $promptID)->first();
    }

    public function store(
        int $promptID,
        string $systemCode,
        string $group,
        string $name,
        string $system,
        string $message,
        array $inputs = []
    ): Prompt
    {
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
        $prompt->save();

        return $prompt;
    }

    public function replaceInputs(string $string, array $inputs): string
    {
        foreach ($inputs as $key => $value) {
            $string = str_replace('{' . $key . '}', $value, $string);
        }

        return $string;
    }
}
