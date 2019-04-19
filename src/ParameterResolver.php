<?php

namespace Spatie\LaravelEndpointResources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Illuminate\Routing\Route;
use ReflectionParameter;

final class ParameterResolver
{
    /** @var \Illuminate\Database\Eloquent\Model|null */
    private $model;

    /** @var array */
    private $defaultParameters;

    public function __construct(?Model $model, array $defaultParameters = [])
    {
        $this->model = $model;
        $this->defaultParameters = $defaultParameters;
    }

    public function forRoute(Route $route): array
    {
        $providedParameters = $this->getProvidedParameters();

        return collect($route->signatureParameters())
            ->mapWithKeys(function (ReflectionParameter $signatureParameter) use ($providedParameters) {
                return [
                    $signatureParameter->getName() => $this->resolveParameter(
                        $signatureParameter,
                        $providedParameters
                    ),
                ];
            })
            ->reject(function ($parameter) {
                return $parameter === null;
            })->all();
    }

    public function canRouteBeConstructed(Route $route): bool
    {
        try {
            action($route->getActionName(), $this->forRoute($route));

            return true;
        } catch (UrlGenerationException $e) {
            return false;
        }
    }

    private function getProvidedParameters(): array
    {
        return optional($this->model)->exists
            ? array_merge([$this->model], $this->defaultParameters)
            : $this->defaultParameters;
    }

    private function resolveParameter(
        ReflectionParameter $signatureParameter,
        array $providedParameters
    ) {
        if (array_key_exists($signatureParameter->getName(), $providedParameters)) {
            return $providedParameters[$signatureParameter->getName()];
        }

        foreach ($providedParameters as $providedParameter) {
            if (! is_object($providedParameter)) {
                continue;
            }

            if ($signatureParameter->getType()->getName() === get_class($providedParameter)) {
                return $providedParameter;
            }
        }

        return null;
    }
}