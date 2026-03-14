<?php

namespace App\Http\Controllers;

abstract class Controller
{
    public function middleware($middleware, array $options = [])
    {
        foreach (($middleware = is_array($middleware) ? $middleware : [$middleware]) as $m) {
            if (! isset($this->middleware[$m])) {
                $this->middleware[$m] = [
                    'middleware' => $m,
                    'options' => $options,
                ];
            }
        }

        return $this;
    }
}
