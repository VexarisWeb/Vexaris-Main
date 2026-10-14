<?php
// Gelen e-posta HTML gorunumu icin allowlist tabanli temizleyici + sandbox iframe ile cift katman.
function vx_clean_html(string $html, string $level = 'rich'): string {
    $html = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $html);
    if ($level === 'inline') {
        $allowed = ['b', 'strong', 'i', 'em', 'u', 'span', 'br'];
    } elseif ($level === 'basic') {
        $allowed = ['b', 'strong', 'i', 'em', 'u', 'a', 'span', 'font', 'br', 'p', 'div'];
    } else {
        $allowed = ['b', 'strong', 'i', 'em', 'u', 'a', 'span', 'font', 'br', 'p', 'div', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'img', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'blockquote', 'pre', 'code', 'hr'];
    }
    if (!class_exists('DOMDocument')) return vx_clean_html_regex($html, $allowed);
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $wraps = $doc->getElementsByTagName('div');
    $wrap = ($wraps->length > 0) ? $wraps->item(0) : $doc->documentElement;
    if (!$wrap) return '';
    vx_clean_html_walk($wrap, $allowed);
    $out = '';
    foreach ($wrap->childNodes as $child) {
        $h = $doc->saveHTML($child);
        if (is_string($h)) $out .= $h;
    }
    return $out;
}

function vx_clean_html_walk(DOMNode $node, array $allowed): void {
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select', 'textarea', 'option', 'link', 'meta', 'base', 'title', 'frame', 'frameset', 'noembed', 'noframes', 'noscript', 'template', 'slot', 'xmp', 'plaintext', 'listing', 'marquee', 'blink'];
    $kids = [];
    foreach ($node->childNodes as $c) $kids[] = $c;
    foreach ($kids as $c) {
        if ($c->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($c->nodeName);
            if (!in_array($tag, $allowed, true)) {
                if (in_array($tag, $drop, true)) {
                    $node->removeChild($c);
                } else {
                    vx_clean_html_walk($c, $allowed);
                    while ($c->firstChild) $node->insertBefore($c->firstChild, $c);
                    $node->removeChild($c);
                }
                continue;
            }
            if ($c instanceof DOMElement) vx_clean_html_attrs($c);
            vx_clean_html_walk($c, $allowed);
        } elseif ($c->nodeType === XML_COMMENT_NODE || $c->nodeType === XML_PI_NODE) {
            $node->removeChild($c);
        }
    }
}

function vx_clean_html_attrs(DOMElement $el): void {
    $urlAttrs = ['href', 'src', 'srcset', 'xlink:href', 'cite', 'action', 'formaction', 'poster', 'background'];
    $remove = [];
    foreach ($el->attributes as $a) {
        $name = strtolower($a->nodeName);
        $val = (string)$a->nodeValue;
        if (strncmp($name, 'on', 2) === 0 || $name === 'xmlns' || strncmp($name, 'xmlns:', 6) === 0) {
            $remove[] = $a->nodeName;
            continue;
        }
        if (in_array($name, $urlAttrs, true)) {
            $v = trim($val);
            if ($name === 'href') {
                if (!preg_match('#^(https?://|mailto:|/|\#)#i', $v)) $el->setAttribute($a->nodeName, '#');
            } elseif (!preg_match('#^(https?://|/|data:image/(png|jpeg|gif|webp);)#i', $v)) {
                $remove[] = $a->nodeName;
            }
            continue;
        }
        if ($name === 'style') {
            $low = strtolower($val);
            if (strpos($low, 'expression') !== false || strpos($low, 'javascript:') !== false || strpos($low, 'vbscript:') !== false || strpos($low, 'behavior') !== false || strpos($low, '-moz-binding') !== false) $remove[] = $a->nodeName;
        }
    }
    foreach ($remove as $n) $el->removeAttribute($n);
}

function vx_clean_html_regex(string $html, array $allowed): string {
    $s = strip_tags($html, '<' . implode('><', $allowed) . '>');
    $s = (string)preg_replace('/[\s\/]\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s);
    $s = (string)preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s);
    $s = (string)preg_replace('/\sxmlns(:\w+)?\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $s);
    $s = (string)preg_replace_callback('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/i', function ($m) {
        $v = strtolower($m[1]);
        if (strpos($v, 'expression') !== false || strpos($v, 'javascript:') !== false || strpos($v, 'vbscript:') !== false || strpos($v, 'behavior') !== false || strpos($v, '-moz-binding') !== false) return '';
        return $m[0];
    }, $s);
    $s = (string)preg_replace_callback('/(href|src|srcset|xlink:href|cite|action|formaction|poster|background)\s*=\s*("[^"]*"|\'[^\']*\')/i', function ($m) {
        $attr = strtolower($m[1]);
        $q = $m[2][0];
        $u = trim(substr($m[2], 1, -1));
        if ($attr === 'href') {
            if (!preg_match('#^(https?://|mailto:|/|\#)#i', $u)) $u = '#';
        } else {
            if (!preg_match('#^(https?://|/|data:image/(png|jpeg|gif|webp);)#i', $u)) return '';
        }
        return $m[1] . '=' . $q . $u . $q;
    }, $s);
    $s = (string)preg_replace_callback('/\s(href|src|srcset|xlink:href|cite|action|formaction|poster|background)\s*=\s*([^\s>"\']+)/i', function ($m) {
        $attr = strtolower($m[1]);
        $u = trim($m[2]);
        if ($attr === 'href') {
            if (!preg_match('#^(https?://|mailto:|/|\#)#i', $u)) return ' href="#"';
        } else {
            if (!preg_match('#^(https?://|/|data:image/(png|jpeg|gif|webp);)#i', $u)) return '';
        }
        return $m[0];
    }, $s);
    $s = (string)preg_replace('/<(script|style|iframe|object|embed|applet|form|input|button|select|textarea|option|link|meta|base|title|frame|frameset|noembed|noframes|noscript|template|slot|svg|math|xmp|plaintext|listing|marquee|blink)[^>]*>.*?<\/\1\s*>/is', '', $s);
    $s = (string)preg_replace('/<(script|style|iframe|object|embed|applet|form|input|button|select|textarea|option|link|meta|base|title|frame|frameset|noembed|noframes|noscript|template|slot|svg|math|xmp|plaintext|listing|marquee|blink)[^>]*\/?>/i', '', $s);
    return $s;
}