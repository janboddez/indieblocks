<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita41f68a5b79b4678cf41c80073cd864a
{
    public static $files = array (
        '757772e28a0943a9afe83def8db95bdf' => __DIR__ . '/..' . '/mf2/mf2/Mf2/Parser.php',
        'a01125dfebcda7ec3333dcd2d57ad8f2' => __DIR__ . '/../..' . '/../includes/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'IndieBlocks\\Mimey\\' => 18,
            'IndieBlocks\\Michelf\\' => 20,
            'IndieBlocks\\Masterminds\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'IndieBlocks\\Mimey\\' => 
        array (
            0 => __DIR__ . '/..' . '/ralouphie/mimey/src',
        ),
        'IndieBlocks\\Michelf\\' => 
        array (
            0 => __DIR__ . '/..' . '/michelf/php-markdown/Michelf',
        ),
        'IndieBlocks\\Masterminds\\' => 
        array (
            0 => __DIR__ . '/..' . '/masterminds/html5/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'IndieBlocks\\Blocks' => __DIR__ . '/../..' . '/../includes/class-blocks.php',
        'IndieBlocks\\Commands\\Commands' => __DIR__ . '/../..' . '/../includes/commands/class-commands.php',
        'IndieBlocks\\Feeds' => __DIR__ . '/../..' . '/../includes/class-feeds.php',
        'IndieBlocks\\Image_Proxy\\Image_Proxy' => __DIR__ . '/../..' . '/../includes/image-proxy/class-image-proxy.php',
        'IndieBlocks\\Location' => __DIR__ . '/../..' . '/../includes/class-location.php',
        'IndieBlocks\\Masterminds\\HTML5' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5.php',
        'IndieBlocks\\Masterminds\\HTML5\\Elements' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Elements.php',
        'IndieBlocks\\Masterminds\\HTML5\\Entities' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Entities.php',
        'IndieBlocks\\Masterminds\\HTML5\\Exception' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Exception.php',
        'IndieBlocks\\Masterminds\\HTML5\\InstructionProcessor' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/InstructionProcessor.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\CharacterReference' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/CharacterReference.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\DOMTreeBuilder' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/DOMTreeBuilder.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\EventHandler' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/EventHandler.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\FileInputStream' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/FileInputStream.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\InputStream' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/InputStream.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\ParseError' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/ParseError.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\Scanner' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/Scanner.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\StringInputStream' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/StringInputStream.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\Tokenizer' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/Tokenizer.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\TreeBuildingRules' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/TreeBuildingRules.php',
        'IndieBlocks\\Masterminds\\HTML5\\Parser\\UTF8Utils' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Parser/UTF8Utils.php',
        'IndieBlocks\\Masterminds\\HTML5\\Serializer\\HTML5Entities' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Serializer/HTML5Entities.php',
        'IndieBlocks\\Masterminds\\HTML5\\Serializer\\OutputRules' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Serializer/OutputRules.php',
        'IndieBlocks\\Masterminds\\HTML5\\Serializer\\RulesInterface' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Serializer/RulesInterface.php',
        'IndieBlocks\\Masterminds\\HTML5\\Serializer\\Traverser' => __DIR__ . '/..' . '/masterminds/html5/src/HTML5/Serializer/Traverser.php',
        'IndieBlocks\\Michelf\\Markdown' => __DIR__ . '/..' . '/michelf/php-markdown/Michelf/Markdown.php',
        'IndieBlocks\\Michelf\\MarkdownExtra' => __DIR__ . '/..' . '/michelf/php-markdown/Michelf/MarkdownExtra.php',
        'IndieBlocks\\Michelf\\MarkdownInterface' => __DIR__ . '/..' . '/michelf/php-markdown/Michelf/MarkdownInterface.php',
        'IndieBlocks\\Micropub_Compat' => __DIR__ . '/../..' . '/../includes/class-micropub-compat.php',
        'IndieBlocks\\Mimey\\MimeMappingGenerator' => __DIR__ . '/..' . '/ralouphie/mimey/src/MimeMappingGenerator.php',
        'IndieBlocks\\Mimey\\MimeTypes' => __DIR__ . '/..' . '/ralouphie/mimey/src/MimeTypes.php',
        'IndieBlocks\\Mimey\\MimeTypesInterface' => __DIR__ . '/..' . '/ralouphie/mimey/src/MimeTypesInterface.php',
        'IndieBlocks\\Options_Handler' => __DIR__ . '/../..' . '/../includes/class-options-handler.php',
        'IndieBlocks\\Parser' => __DIR__ . '/../..' . '/../includes/class-parser.php',
        'IndieBlocks\\Plugin' => __DIR__ . '/../..' . '/../includes/class-plugin.php',
        'IndieBlocks\\Post_Types' => __DIR__ . '/../..' . '/../includes/class-post-types.php',
        'IndieBlocks\\Preview_Cards' => __DIR__ . '/../..' . '/../includes/class-preview-cards.php',
        'IndieBlocks\\Theme_Mf2' => __DIR__ . '/../..' . '/../includes/class-theme-mf2.php',
        'IndieBlocks\\Webmention\\Webmention' => __DIR__ . '/../..' . '/../includes/webmention/class-webmention.php',
        'IndieBlocks\\Webmention\\Webmention_Parser' => __DIR__ . '/../..' . '/../includes/webmention/class-webmention-parser.php',
        'IndieBlocks\\Webmention\\Webmention_Receiver' => __DIR__ . '/../..' . '/../includes/webmention/class-webmention-receiver.php',
        'IndieBlocks\\Webmention\\Webmention_Sender' => __DIR__ . '/../..' . '/../includes/webmention/class-webmention-sender.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita41f68a5b79b4678cf41c80073cd864a::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita41f68a5b79b4678cf41c80073cd864a::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInita41f68a5b79b4678cf41c80073cd864a::$classMap;

        }, null, ClassLoader::class);
    }
}
