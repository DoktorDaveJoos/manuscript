<?php

use App\Polyfills\XmlWriter;

// Aliases App\Polyfills\XmlWriter as global \XMLWriter when ext-xmlwriter is
// missing — see App\Polyfills\XmlWriter for the why.

if (! extension_loaded('xmlwriter') && ! class_exists('XMLWriter', false)) {
    class_alias(XmlWriter::class, 'XMLWriter');
}
