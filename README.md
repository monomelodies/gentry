# Gentry
A unit testing framework for PHP5.5+

Good programmers are lazy, but unfortunately that means that stuff like writing
unit tests (boooooring) is often skipped. Please don't; it's important and oh
so handy once you have them in place.

Gentry was designed with three goals in mind:

1. To make writing unit tests _so_ easy even the worst slacker will bother;
2. To alleviate writing boilerplate code by generating skeletons for you.
3. Speed. You want to run tests before you push, so if they're slow that's
   _annoying_.

## Installation

### Composer (recommended)
```sh
composer require --dev monomelodies/gentry
```

You can now run `vendor/bin/gentry`.

### Manual
Download or clone the repo. There's an executable in the root.

## Configuration
Create a `Gentry.json` file in the root of your project. It uses the following
options:

```json
{
    "src": "/path/to/src",
    "tests": "/path/to/tests",
    "includePath": "/my/include/path",
    "bootstrap": "/path/to/bootstrap.php",
    "namespace": "Foo",
    "ignore": "some.*?regex"
}
```

### string `src` ###
### string `tests` ###
Gentry makes two assumptions:

1. Your source files are in a directory (`"/path/to/src"`).
2. Your tests are in another directory (`"path/to/tests"`).

If these two are mixed, clean that up first. Seriously.

Both `src` and `tests` can be either absolute, or relative to the root - hence
`"/path/to/root/src"` could be simplified to just `"src"`.

### string|array `bootstrap` ###
The path(s) to file(s) ("bootstrapper(s)") every piece of code in your
application needs. This is usually something that would reside in an `index.php`
entry point or similar file. These files are otherwise ignored by Gentry when
analysing your code and should do stuff like initialise an autoloader.

You can also pass an array of files instead of a string. They will be prepended
in order.

`includePath` is parsed before `bootstrap`, so if you use them in conjunction
you could use relative paths here. Otherwise, they will be relative to
`get_cwd()`.

### string `ignore` ###
A regular expression of classnames to ignore in the `"src"` path. Useful for
automatically ignoring classtypes that are hard to test, e.g. controllers. You
could also utilise this if your tests and sourcecode are mixed (but seriously,
don't do that).

## Usage
Now run Gentry from the command line and see what happens:

```sh
vendor/bin/gentry
```

It'll complain that it can't do anything yet. Which makes sense, we haven't
written any tests yet!

## Verbose mode
If you'd like more info, run Gentry with the `-v` flag:

```sh
vendor/bin/gentry -v
```

In the default mode, only important messages are displayed. But verbose mode
might be handy when something's going wrong for you, or if you simply want
feedback about stuff like incomplete tests.

## Detecting the environment
For a lot of testing, you'll need to detect whether or not to use a mock object
(e.g. for database connections), or "the real thang". The simplest way is to
call `defined('Gentry\COMPOSER_INSTALL') since that constant is defined before
Gentry does _anything_ else.

Usually you'll do this in a `"bootstrap"` file. This could also setup
superglobals like `$_SERVER` if you're testing controllers or such.

## Generating missing tests
Run Gentry with the `-g` flag to generate skeletons for missing tests for you:

```sh
vendor/bin/gentry -g
```

More on generating tests in the corresponding section of the manual.

