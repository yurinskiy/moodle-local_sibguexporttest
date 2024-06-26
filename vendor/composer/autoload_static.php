<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit761ced7904d2c3a58e420f596b36d1ab
{
    public static $prefixLengthsPsr4 = array (
        'm' => 
        array (
            'mikehaertl\\wkhtmlto\\' => 20,
            'mikehaertl\\tmp\\' => 15,
            'mikehaertl\\shellcommand\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'mikehaertl\\wkhtmlto\\' => 
        array (
            0 => __DIR__ . '/..' . '/mikehaertl/phpwkhtmltopdf/src',
        ),
        'mikehaertl\\tmp\\' => 
        array (
            0 => __DIR__ . '/..' . '/mikehaertl/php-tmpfile/src',
        ),
        'mikehaertl\\shellcommand\\' => 
        array (
            0 => __DIR__ . '/..' . '/mikehaertl/php-shellcommand/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit761ced7904d2c3a58e420f596b36d1ab::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit761ced7904d2c3a58e420f596b36d1ab::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit761ced7904d2c3a58e420f596b36d1ab::$classMap;

        }, null, ClassLoader::class);
    }
}
