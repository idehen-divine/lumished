<?php

namespace App\Traits;

trait NormalizesTextInputs
{
    /**
     * Trim and lowercase the given request fields before validation.
     *
     * @param  array<int, string>  $fields
     */
    protected function lowercaseFields(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = mb_strtolower(trim($this->input($field)));
            }
        }

        $this->merge($normalized);
    }

    /**
     * Trim, lowercase, then uppercase the first letter of the given request fields.
     *
     * @param  array<int, string>  $fields
     */
    protected function ucfirstFields(array $fields): void
    {
        $normalized = [];

        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $normalized[$field] = ucfirst(mb_strtolower(trim($this->input($field))));
            }
        }

        $this->merge($normalized);
    }
}
