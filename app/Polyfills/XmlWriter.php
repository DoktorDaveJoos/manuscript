<?php

namespace App\Polyfills;

/**
 * Userland implementation of PHP's XMLWriter, registered as global \XMLWriter
 * via class_alias when ext-xmlwriter is unavailable (see app/Polyfills/load.php).
 *
 * Why: NativePHP's bundled PHP binary (vendor/nativephp/php-bin) ships without
 * ext-xmlwriter. PhpWord's Word2007 writer requires it, breaking docx export
 * in the desktop app. Tracked upstream at github.com/nativephp/php-bin —
 * remove this polyfill once the next php-bin release includes xmlwriter.
 *
 * Implements only the surface PhpWord's Word2007 writer uses (verified by
 * grep against vendor/phpoffice/phpword/src/PhpWord/Writer/Word2007).
 *
 * Signatures are intentionally untyped: PhpWord's Shared\XMLWriter extends
 * \XMLWriter and overrides writeAttribute() with an untyped signature. PHP's
 * #[ReturnTypeWillChange] only relaxes the LSP check against internal classes
 * — when the parent is userland (this polyfill), strict signature matching
 * applies. Loose signatures match PhpWord's contract.
 */
class XmlWriter
{
    private $buffer = '';

    private $uri = null;

    private $stack = [];

    private $tagOpen = false;

    private $hasChildren = false;

    private $indent = false;

    private $indentStr = ' ';

    public function openMemory()
    {
        $this->reset();

        return true;
    }

    public function openUri($uri)
    {
        $this->reset();
        $this->uri = $uri;
        if ($uri !== '') {
            @file_put_contents($uri, '');
        }

        return true;
    }

    public function setIndent($indent)
    {
        $this->indent = (bool) $indent;

        return true;
    }

    public function setIndentString($indentString)
    {
        $this->indentStr = (string) $indentString;

        return true;
    }

    public function startDocument($version = '1.0', $encoding = null, $standalone = null)
    {
        $parts = ['version="'.$version.'"'];
        if ($encoding !== null) {
            $parts[] = 'encoding="'.$encoding.'"';
        }
        if ($standalone !== null) {
            $parts[] = 'standalone="'.$standalone.'"';
        }
        $this->buffer .= '<?xml '.implode(' ', $parts).'?>';
        if ($this->indent) {
            $this->buffer .= "\n";
        }

        return true;
    }

    public function endDocument()
    {
        while (! empty($this->stack)) {
            $this->endElement();
        }

        return true;
    }

    public function startElement($name)
    {
        $this->closeOpenTag();
        if ($this->indent && $this->buffer !== '' && substr($this->buffer, -1) !== "\n") {
            $this->buffer .= "\n";
        }
        if ($this->indent) {
            $this->buffer .= str_repeat($this->indentStr, count($this->stack));
        }
        $this->buffer .= '<'.$name;
        $this->stack[] = $name;
        $this->tagOpen = true;
        $this->hasChildren = false;

        return true;
    }

    public function endElement()
    {
        if (empty($this->stack)) {
            return false;
        }
        $name = array_pop($this->stack);
        if ($this->tagOpen) {
            $this->buffer .= '/>';
            $this->tagOpen = false;
        } else {
            if ($this->indent && $this->hasChildren) {
                $this->buffer .= "\n".str_repeat($this->indentStr, count($this->stack));
            }
            $this->buffer .= '</'.$name.'>';
        }
        $this->hasChildren = true;

        return true;
    }

    public function writeAttribute($name, $value)
    {
        if (! $this->tagOpen) {
            return false;
        }
        $this->buffer .= ' '.$name.'="'.$this->escapeAttribute((string) $value).'"';

        return true;
    }

    public function text($content)
    {
        $this->closeOpenTag();
        $this->buffer .= $this->escapeText((string) $content);
        $this->hasChildren = false;

        return true;
    }

    public function writeRaw($content)
    {
        $this->closeOpenTag();
        $this->buffer .= (string) $content;
        $this->hasChildren = false;

        return true;
    }

    public function writeElement($name, $content = null)
    {
        $this->startElement($name);
        if ($content !== null && $content !== '') {
            $this->text($content);
        }
        $this->endElement();

        return true;
    }

    public function outputMemory($flush = true)
    {
        $out = $this->buffer;
        if ($flush) {
            $this->buffer = '';
        }

        return $out;
    }

    public function flush($empty = true)
    {
        if ($this->uri !== null && $this->uri !== '') {
            $bytes = @file_put_contents($this->uri, $this->buffer, FILE_APPEND);
            if ($empty) {
                $this->buffer = '';
            }

            return $bytes === false ? 0 : $bytes;
        }

        $bytes = strlen($this->buffer);
        if ($empty) {
            $this->buffer = '';
        }

        return $bytes;
    }

    private function reset()
    {
        $this->buffer = '';
        $this->uri = null;
        $this->stack = [];
        $this->tagOpen = false;
        $this->hasChildren = false;
    }

    private function closeOpenTag()
    {
        if ($this->tagOpen) {
            $this->buffer .= '>';
            $this->tagOpen = false;
        }
    }

    private function escapeText($content)
    {
        return str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $content,
        );
    }

    private function escapeAttribute($value)
    {
        return str_replace(
            ['&', '<', '>', '"'],
            ['&amp;', '&lt;', '&gt;', '&quot;'],
            $value,
        );
    }
}
