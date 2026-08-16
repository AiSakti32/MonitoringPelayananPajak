<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirectWithErrors(string $path, array $errors, array $old = []): never
    {
        Session::flash('errors', $errors);
        if ($old !== []) {
            Session::flash('old', $old);
        }
        redirect($path);
    }

    protected function redirectWithSuccess(string $path, string $message): never
    {
        Session::flash('success', $message);
        redirect($path);
    }
}
