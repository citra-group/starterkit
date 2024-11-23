<?php

namespace CitraGroup\Platform;

use SplFileInfo;
use ReflectionClass;
use ReflectionMethod;
use ReflectionException;
use Illuminate\Support\Str;
use Illuminate\Support\Reflector;
use Illuminate\Foundation\Events\DiscoverEvents as BaseDiscoveryEvents;

class DiscoverEvents extends BaseDiscoveryEvents
{
    /**
     * Get all of the listeners and their corresponding events.
     *
     * @param  iterable  $listeners
     * @param  string  $basePath
     * @return array
     */
    protected static function getListenerEvents($listeners, $basePath)
    {
        $listenerEvents = [];

        foreach ($listeners as $listener) {
            try {
                $listener = new ReflectionClass(
                    static::classFromFile($listener, $basePath)
                );
            } catch (ReflectionException) {
                continue;
            }

            if (! $listener->isInstantiable()) {
                continue;
            }


            foreach ($listener->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ((! Str::is('handle*', $method->name) && ! Str::is('__invoke', $method->name)) ||
                    ! isset($method->getParameters()[0])
                ) {
                    continue;
                }

                $listenerEvents[$listener->name . '@' . $method->name] =
                    Reflector::getParameterClassNames($method->getParameters()[0]);
            }
        }

        return array_filter($listenerEvents);
    }

    /**
     * Extract the class name from the given file path.
     *
     * @param  \SplFileInfo  $file
     * @param  string  $basePath
     * @return string
     */
    protected static function classFromFile(SplFileInfo $file, $basePath)
    {
        if (static::$guessClassNamesUsingCallback) {
            return call_user_func(static::$guessClassNamesUsingCallback, $file, $basePath);
        }

        $class = trim(Str::replaceFirst($basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);

        $module = ucfirst(Str::of($class)->before('/src')->after('modules/'));

        $class = 'Module' . DIRECTORY_SEPARATOR . $module . Str::of($class)->after('src');

        return str_replace(
            [DIRECTORY_SEPARATOR, ucfirst(basename(app()->path())) . '\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );
    }
}