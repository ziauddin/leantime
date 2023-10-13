<?php

namespace Leantime\Core;

use Illuminate\Contracts\Container\BindingResolutionException;
use Leantime\Core\Plugins;

/**
 * Events class - Handles all events and filters
 *
 * @package    leantime
 * @subpackage core
 */
class Events
{
    /**
     * Registry of all events added to a hook
     *
     * @var array
     */
    private static array $eventRegistry = [];

    /**
     * Registry of all filters added to a hook
     *
     * @var array
     */
    private static array $filterRegistry = [];

    /**
     * Registry of all hooks available
     *
     * @var array
     */
    private static array $available_hooks = [
        'filters' => [],
        'events' => [],
    ];

    /**
     * Dispatches an event to be executed somewhere
     *
     * @access public
     *
     * @param string $eventName
     * @param mixed  $payload
     * @param string $context
     *
     * @return void
     * @throws BindingResolutionException
     */
    public static function dispatch_event(
        string $eventName,
        mixed $payload = [],
        string $context = ''
    ): void {
        $eventName = "$context.$eventName";

        if (!in_array($eventName, self::$available_hooks['events'])) {
            self::$available_hooks['events'][] = $eventName;
        }

        $matchedEvents = self::findEventListeners($eventName, self::$eventRegistry);
        if (count($matchedEvents) == 0) {
            return;
        }

        $payload = self::defineParams($payload);

        self::executeHandlers($matchedEvents, "events", $eventName, $payload);
    }


    /**
     * Finds event listeners by event names,
     * Allows listeners with wildcards
     *
     * @access public
     *
     * @param string $eventName
     * @param array  $registry
     *
     * @return array
     */
    public static function findEventListeners(string $eventName, array $registry): array
    {
        $matches = [];

        foreach ($registry as $key => $value) {
            preg_match_all('/\{RGX:(.*?):RGX\}/', $key, $regexMatches);

            $key = strtr($key, [
                ...collect($regexMatches[0] ?? [])->mapWithKeys(fn ($match, $i) => [$match => "REGEX_MATCH_$i"])->toArray(),
                '*' => 'RANDOM_STRING',
                '?' => 'RANDOM_CHARACTER',
            ]);

            // escape the non regex characters
            $pattern = preg_quote($key, '/');

            $pattern = strtr($pattern, [
                'RANDOM_STRING' => '.*?', // 0 or more (lazy) - asterisk (*)
                'RANDOM_CHARACTER' => '.', // 1 character - question mark (?)
                ...collect($regexMatches[1] ?? [])->mapWithKeys(fn ($match, $i) => ["REGEX_MATCH_$i" => $match])->toArray(),
            ]);

            if (preg_match("/^$pattern$/", $eventName)) {
                $matches = array_merge($matches, $value);
            }
        }

        return $matches;
    }


    /**
     * Dispatches a filter to manipulate a variable somewhere
     *
     * @access public
     *
     * @param string $filtername
     * @param mixed  $payload
     * @param mixed  $available_params
     * @param mixed  $context
     *
     * @return mixed
     * @throws BindingResolutionException
     */
    public static function dispatch_filter(
        string $filtername,
        mixed $payload = '',
        mixed $available_params = [],
        mixed $context = ''
    ): mixed {
        $filtername = "$context.$filtername";

        if (!in_array($filtername, self::$available_hooks['filters'])) {
            self::$available_hooks['filters'][] = $filtername;
        }

        $matchedEvents = self::findEventListeners($filtername, self::$filterRegistry);
        if (count($matchedEvents) == 0) {
            return $payload;
        }

        $available_params = self::defineParams($available_params);

        return self::executeHandlers($matchedEvents, "filters", $filtername, $payload, $available_params);
    }

    /**
     * Finds all the event and filter listeners and registers them
     * (should only be executed once at the beginning of the program)
     *
     * @access public
     *
     * @return void
     * @throws BindingResolutionException
     */
    public static function discover_listeners(): void
    {
        if (empty($_SESSION['domainEvents']) || app()->make(Environment::class)->debug) {
            $customModules = collect(glob(APP_ROOT . '/custom/Domain' . '/*', GLOB_ONLYDIR));
            $domainModules = collect(glob(APP_ROOT . "/app/Domain" . '/*', GLOB_ONLYDIR));

            $testers = $customModules->map(fn ($path) => str_replace('/custom/', '/app/', $path));

            $filteredModules = $domainModules->filter(fn ($path) => ! $testers->contains($path));

            $_SESSION['domainEvents'] = $customModules->concat($filteredModules)->all();
        }

        $modules = $_SESSION['domainEvents'];
        foreach ($modules as $module) {
            if (file_exists($module . "/Events/register.php")) {
                include $module . "/Events/register.php";
            }
        }

        if (
            isset(app(Environment::class)->plugins)
            && $configplugins = explode(',', app(Environment::class)->plugins)
        ) {
            foreach ($configplugins as $plugin) {
                if (file_exists(APP_ROOT . "/app/Plugins/" . $plugin . "/register.php")) {
                    include_once APP_ROOT . "/app/Plugins/" . $plugin . "/register.php";
                }
            }
        }

        Events::add_event_listener('leantime.core.bootloader.boot.after_install', function () {
            $enabledPlugins = [];
            $pluginPath = APP_ROOT . "/app/Plugins/";
            if ($_SESSION['isInstalled'] === true) {
                $pluginService = app()->make(\Leantime\Domain\Plugins\Services\Plugins::class);
                $enabledPlugins = $pluginService->getEnabledPlugins();
            }

            foreach ($enabledPlugins as $plugin) {
                if ($plugin != null) {

                    if($plugin->format == "phar") {

                        $path = "phar://".$pluginPath . $plugin->foldername . "/". $plugin->foldername .".phar/register.php";

                         if (file_exists("phar://".$pluginPath . $plugin->foldername . "/". $plugin->foldername .".phar/register.php")) {
                             //If it's the first time loading the plugin, load phar
                             include_once "phar://".$pluginPath . $plugin->foldername . "/". $plugin->foldername .".phar";
                             include_once "phar://".$pluginPath . $plugin->foldername . "/". $plugin->foldername .".phar/register.php";
                         }

                    }else{

                        if (file_exists($pluginPath . $plugin->foldername . "/register.php")) {
                            include_once $pluginPath . $plugin->foldername . "/register.php";
                        }

                    }


                }
            }
        });
    }

    /**
     * Adds an event listener to be registered
     *
     * @access public
     *
     * @param string                 $eventName
     * @param string|callable|object $handler
     * @param int                    $priority
     *
     * @return void
     */
    public static function add_event_listener(
        string $eventName,
        string|callable|object $handler,
        int $priority = 10
    ): void {
        if (! key_exists($eventName, self::$eventRegistry)) {
            self::$eventRegistry[$eventName] = [];
        }
        self::$eventRegistry[$eventName][] = array("handler" => $handler, "priority" => $priority);
    }

    /**
     * Adds a filter listener to be registered
     *
     * @access public
     *
     * @param string                 $filtername
     * @param string|callable|object $handler
     * @param int                    $priority
     *
     * @return void
     */
    public static function add_filter_listener(
        string $filtername,
        string|callable|object $handler,
        int $priority = 10
    ): void {
        if (! key_exists($filtername, self::$filterRegistry)) {
            self::$filterRegistry[$filtername] = [];
        }
        self::$filterRegistry[$filtername][] = array("handler" => $handler, "priority" => $priority);
    }

    /**
     * Gets all registered listeners
     *
     * @access public
     *
     * @return array
     */
    public static function get_registries(): array
    {
        return [
            'events' => array_keys(self::$eventRegistry),
            'filters' => array_keys(self::$filterRegistry),
        ];
    }

    /**
     * Gets all available hooks
     *
     * @access public
     *
     * @return array
     */
    public static function get_available_hooks(): array
    {
        return self::$available_hooks;
    }

    /**
     * Sorts listeners by priority for a given hook and type
     *
     * @access private
     *
     * @param string $type
     * @param string $hookName
     *
     * @return void
     */
    private static function sortByPriority(string $type, string $hookName): void
    {
        if ($type !== 'filters' && $type !== 'events') {
            return;
        }

        $sorter = function ($a, $b) {
            if ($a['priority'] > $b['priority']) {
                return 1;
            } elseif ($a['priority'] == $b['priority']) {
                return 0;
            } else {
                return -1;
            }
        };



        if ($type == 'filters') {
            usort(self::$filterRegistry[$hookName], $sorter);
        } elseif ($type == 'events') {
            usort(self::$eventRegistry[$hookName], $sorter);
        }
    }

    /**
     * Adds the current_route to the event's/filter's available params
     *
     * @access private
     *
     * @param mixed $paramAttr
     *
     * @return array|object
     * @throws BindingResolutionException
     */
    private static function defineParams(mixed $paramAttr): array|object
    {
        // make this static so we only have to call once
        static $default_params;

        if (!isset($default_params)) {
            $default_params = [
                'current_route' => Frontcontroller::getCurrentRoute(),
            ];
        }

        $finalParams = [];

        if (is_array($paramAttr)) {
            $finalParams = array_merge($default_params, $paramAttr);
            return $finalParams;
        }

        if (is_object($paramAttr)) {
            $finalParams = (object) array_merge($default_params, (array) $paramAttr);
            return $finalParams;
        }

        $finalParams = $default_params;
        array_push($finalParams, $paramAttr);

        return $finalParams;
    }

    /**
     * Executes all the handlers for a given hook
     *
     * @access private
     *
     * @param array        $registry
     * @param string       $registryType
     * @param string       $hookName
     * @param mixed        $payload
     * @param array|object $available_params
     *
     * @return array|object|null
     */
    private static function executeHandlers(
        array $registry,
        string $registryType,
        string $hookName,
        mixed $payload,
        array|object $available_params = []
    ): mixed {

        $isEvent = $registryType == "events";
        $filteredPayload = null;

        //sort matches by priority
        usort($registry, function ($a, $b) {
            if ($a['priority'] > $b['priority']) {
                return 1;
            } elseif ($a['priority'] == $b['priority']) {
                return 0;
            } else {
                return -1;
            }
        });

        foreach ($registry as $index => $listener) {
            $handler = $listener['handler'];

            // class with handle function
            if (is_object($handler) && method_exists($handler, "handle")) {
                if ($isEvent) {
                    $handler->handle($payload);
                    continue;
                }

                $filteredPayload = $handler->handle(
                    $index == 0 ? $payload : $filteredPayload,
                    $available_params
                );
                continue;
            }

            // anonymous functions
            if (is_callable($handler)) {
                if ($isEvent) {
                    $handler($payload);
                    continue;
                }

                $filteredPayload = $handler(
                    $index == 0 ? $payload : $filteredPayload,
                    $available_params
                );
                continue;
            }

            if (
                in_array(true, [
                // function name as string
                is_string($handler) && function_exists($handler),
                // class instance with method name
                is_array($handler) && is_object($handler[0]) && method_exists($handler[0], $handler[1]),
                // class name with method name
                is_array($handler) && class_exists($handler[0]) && method_exists($handler[0], $handler[1]),
                ])
            ) {
                if ($isEvent) {
                    call_user_func_array($handler, [$payload]);
                    continue;
                }

                $filteredPayload = call_user_func_array(
                    $handler,
                    [
                        $index == 0 ? $payload : $filteredPayload,
                        $available_params,
                    ]
                );
                continue;
            }
        }

        if (!$isEvent) {
            return $filteredPayload;
        }

        return null;
    }
}
