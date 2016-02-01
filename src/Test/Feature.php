<?php

namespace Gentry\Test;

use Reflector;
use ReflectionMethod;
use Gentry\Group;
use zpt\anno\Annotations;
use Exception;
use ErrorException;
use Generator;

/**
 * Abstract base class for a feature test. Normally only used internally.
 */
abstract class Feature
{
    protected $description;
    protected $target;
    protected $name;
    protected $class;
    protected $messages = [];
    protected $tested;

    /**
     * Constructor.
     *
     * @param string $description Description of the feature.
     * @param int $target The target to test against, expressed as the argument
     *  number.
     * @param string $name The name of the feature.
     * @param string $class String version of the target's class. Needed for
     *  abstract testing.
     */
    public function __construct($description, $target, $name, $class)
    {
        $this->description = $description;
        $this->target = $target;
        $this->name = $name;
        $this->class = $class;
    }

    /**
     * Assert that for given arguments the expected result(s) are found,
     * optionally piped through a callable.
     *
     * @param array &$args The arguments to use.
     * @param array $expected Hash of expected result, output and/or exception.
     * @param callable $pipe Optional callable to pipe actual result through.
     * @return bool True if the assertion holds, else false.
     */
    public function assert(array &$args, $expected, callable $pipe = null)
    {
        $property = $this instanceof Property;
        if (isset($this->description)) {
            \Gentry\out(str_replace(
                '{'.$this->target.'}',
                "<darkBlue>".$this->testedFeature()."<blue>",
                "<blue>{$this->description}"
            ));
            unset($this->description);
        }
        $actual = $this->actual($args) + ['result' => null];
        if (isset($pipe)) {
            try {
                $actual['result'] = call_user_func($pipe, $actual['result']);
            } catch (Exception $e) {
                $actual['result'] = false;
            }
        }
        $verbs = $property ? ['contain', 'found'] : ['return', 'got'];
        if ($this instanceof Executable) {
            $verbs[0] = 'exit with';
        }
        $this->tested = $this->tostring($args[$this->target]);
        if (!$this->throwCompare($expected['thrown'], $actual['thrown'])) {
            $this->messages[] = sprintf(
                "<gray>Expected %s to throw <darkGray>%s<gray>, caught <darkGray>%s",
                $this->testedFeature(),
                $this->tostring($expected['thrown']),
                $this->tostring($actual['thrown'])
            );
        } elseif (!$this->isEqual($expected['result'], $actual['result'])) {
            $this->messages[] = sprintf(
                "<gray>Expected <darkGray>%s<gray> to %s <darkGray>%s<gray>, %s <darkGray>%s",
                $this->testedFeature(),
                $verbs[0],
                $this->tostring($expected['result']),
                $verbs[1],
                $this->tostring($actual['result'])
            );
        }
        if ($expected['out'] != $actual['out']) {
            $diff = $this->strdiff($expected['out'], $actual['out']);
            $this->messages[] = sprintf(
                "<gray>Expected output for <darkGray>%s<gray>:\n",
                strip_tags($this->testedFeature())
            );
            $this->messages[] = $diff['old']."\n";
            $this->messages[] = "<gray>Actual output:\n";
            $this->messages[] = $diff['new']."\n";
        }
        return $this->isEqual($expected['result'], $actual['result'])
            && $this->throwCompare($expected['thrown'], $actual['thrown'])
            && trim($expected['out']) == trim($actual['out']);
    }

    /**
     * Internal helper method to check if two variables should be considered
     * "equal".
     *
     * @param mixed $a
     * @param mixed $b
     * @return bool True or false depending on equality in a Gentry-context.
     */
    protected function isEqual($a, $b)
    {
        if (is_numeric($a) && is_numeric($b)) {
            return 0 + $a == 0 + $b;
        }
        if (is_object($a) && is_object($b)) {
            return $a == $b;
        }
        if (is_array($a) && is_array($b)) {
            return $this->tostring($a) === $this->tostring($b);
        }
        return $a === $b;
    }

    /**
     * Internal helper method to get an echo'able representation of a random
     * value for reporting.
     *
     * @param mixed $value
     * @return string
     */
    protected function tostring($value)
    {
        if (!isset($value)) {
            return 'NULL';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (is_scalar($value)) {
            return $value;
        }
        if (is_array($value)) {
            $out = 'array(';
            $i = 0;
            foreach ($value as $key => $entry) {
                if ($i) {
                    $out .= ', ';
                }
                $out .= $key.' => '.$this->tostring($entry);
                $i++;
            }
            $out .= ')';
            return $out;
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return "$value";
            } else {
                return get_class($value);
            }
        }
    }

    /**
     * Compares expected and thrown exceptions. To avoid having to mock exception
     * messages, it simply compares classnames and exception codes.
     *
     * @param Exception|null $expected
     * @param Exception|null $actual
     * @return bool True if the exceptions are equal _or_ both are null, else
     *  false.
     */
    protected function throwCompare(Exception $expected = null, Exception $actual = null)
    {
        if (!isset($expected, $actual)) {
            return true;
        }
        if (isset($expected, $actual)) {
            return get_class($expected) == get_class($actual)
                && $expected->getCode() == $actual->getCode();
        }
        return false;
    }

    /**
     * Format expected and actual output a bit nicer.
     *
     * @param string $old Excepted output.
     * @param string $new Actual output.
     * @return array A hash with "old" and "new" keys, prettified using ANSI
     *  colours.
     * @see https://coderwall.com/p/3j2hxq/find-and-format-difference-between-two-strings-in-php
     */
    protected function strdiff($old, $new)
    {
        $old = str_replace("\033[", "<gray>\\033[<reset>\033[", $old);
        $new = str_replace("\033[", "<gray>\\033[<reset>\033[", $new);
        $from_start = strspn($old ^ $new, "\0");
        $from_end = strspn(strrev($old) ^ strrev($new), "\0");
        
        $old_end = strlen($old) - $from_end;
        $new_end = strlen($new) - $from_end;
        
        $start = substr($new, 0, $from_start);
        $end = substr($new, $new_end);
        $new_diff = substr($new, $from_start, $new_end - $from_start);
        $old_diff = substr($old, $from_start, $old_end - $from_start);
        
        $new = "$start<red>$new_diff<reset>$end";
        $old = "$start<green>$old_diff<reset>$end";
        return [
            'old' => preg_replace("@ @ms", "<reset><bgYellow>.<reset>", $old),
            'new' => preg_replace("@ @ms", "<reset><bgYellow>.<reset>", $new),
        ];
    }

    public function testedFeature()
    {
        return sprintf(
            "%s::%s%s",
            $this->class,
           $this instanceof Property ? '$' : '',
            $this->name
        );
    }

    /**
     * Read-only access to certain properties.
     *
     * @param string $prop The property name.
     * @return mixed The property's value if it is readable.
     * @throws ErrorException if not readable.
     */
    public function __get($prop)
    {
        if (in_array($prop, ['name', 'messages', 'tested', 'class'])) {
            return $this->$prop;
        }
        throw new ErrorException("Unreadable property $prop");
    }
    
    /**
     * Helper method to (re)attach default pipes to a testclass on a per-feature
     * basis (since the actual `$result` isn't passed to the closures as an
     * argument). Usually called automatically.
     *
     * @param object &$test The test object to attach to.
     * @param mixed $result Whatever result the test expects.
     */
    public static function addPipes(&$test, $result)
    {
        foreach ([
            'is_a',
            'is_subclass_of',
            'method_exists',
            'property_exists',
        ] as $magic) {
            $test->$magic = function ($res) use ($magic, $result) {
                return call_user_func($magic, $res, $result);
            };
        }
        $test->matches = function ($res) use ($result) {
            return (bool)preg_match($result, $res);
        };
        $test->count = function ($res) use ($result) {
            if ($res instanceof Generator) {
                $i = 0;
                foreach ($res as $item) {
                    $i++;
                }
                return $i == $check;
            } elseif (is_array($res)) {
                return count($res) == $result;
            }
            return false;
        };
    }
}

