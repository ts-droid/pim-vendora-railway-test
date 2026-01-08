<?php

namespace App\Console\Concerns;

trait ProvidesCommandLogContext
{
    protected function commandLogContext(array $extra = []): array
    {
        $context = [
            'command' => property_exists($this, 'signature') ? $this->signature : null,
            'class' => static::class,
        ];

        if (method_exists($this, 'arguments')) {
            try {
                $arguments = $this->arguments();
                if (!empty($arguments)) {
                    $context['arguments'] = $arguments;
                }
            } catch (\Throwable $e) {
                // Ignore gathering arguments failure
            }
        }

        if (method_exists($this, 'options')) {
            try {
                $options = $this->options();
                if (!empty($options)) {
                    $context['options'] = $options;
                }
            } catch (\Throwable $e) {
                // Ignore gathering options failure
            }
        }

        return array_merge($context, $extra);
    }
}
