diff --git a/app/Services/HtmlSanitizer.php b/app/Services/HtmlSanitizer.php
--- a/app/Services/HtmlSanitizer.php
+++ b/app/Services/HtmlSanitizer.php
@@ -1,28 +1,28 @@
 <?php
 
 declare(strict_types=1);
 
 namespace App\Services;
 
 use DOMDocument;
 use DOMElement;
 use DOMNode;
 use Illuminate\Support\Str;
 
 use function collect;
 
 class HtmlSanitizer
 {
-    private const ALLOWED_TAGS = [
+    private const ALLOWED_TAGS = [
         'p',
         'ul',
         'ol',
         'li',
         'a',
         'code',
         'pre',
         'blockquote',
         'strong',
         'em',
         'h1',
         'h2',
         'h3',
     ];
-
-    private const LINK_ATTRIBUTES = ['href', 'rel', 'target'];
+    private const LINK_ATTRIBUTES = ['href', 'rel', 'target'];
 
     public function clean(string $html): string
     {
@@ -34,18 +34,16 @@ class HtmlSanitizer
 
         $document = new DOMDocument('1.0', 'UTF-8');
         $previous = libxml_use_internal_errors(true);
-        $document->loadHTML(
-            '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
-        );
+        $document->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
 
         libxml_clear_errors();
         libxml_use_internal_errors($previous);
 
         $body = $document->getElementsByTagName('body')->item(0);
 
         if ($body === null) {
             return '';
         }
 
         $this->sanitizeNode($body);
 
         $output = '';
@@ -62,7 +60,7 @@ class HtmlSanitizer
         return $output;
     }
 
     private function sanitizeNode(DOMNode $node): void
     {
         if ($node instanceof DOMElement) {
             $tag = Str::lower($node->tagName);
 
             if ($tag === 'script') {
                 $this->removeNode($node);
 
                 return;
             }
 
             if (in_array($tag, self::ALLOWED_TAGS, true) === false) {
                 $children = iterator_to_array($node->childNodes);
 
                 $this->unwrap($node);
 
                 foreach ($children as $child) {
                     $this->sanitizeNode($child);
                 }
 
                 return;
             }
 
             if ($tag === 'a') {
                 $this->sanitizeLink($node);
             } else {
                 $this->stripAttributes($node);
             }
         }
 
         foreach (iterator_to_array($node->childNodes) as $child) {
             $this->sanitizeNode($child);
         }
     }
 
     private function sanitizeLink(DOMElement $element): void
     {
         foreach (iterator_to_array($element->attributes) as $attribute) {
             if (in_array($attribute->name, self::LINK_ATTRIBUTES, true) === false) {
                 $element->removeAttribute($attribute->name);
             }
         }
 
         $href = Str::lower((string) $element->getAttribute('href'));
         if (
             $href === ''
             || Str::startsWith($href, ['http://', 'https://', 'mailto:']) === false
         ) {
             $element->removeAttribute('href');
 
             return;
         }
 
         $target = Str::lower((string) $element->getAttribute('target'));
         if ($target !== '_blank') {
             $element->removeAttribute('target');
             $element->removeAttribute('rel');
 
             return;
         }
 
         $rel = collect(explode(' ', (string) $element->getAttribute('rel')))
             ->filter()
             ->map(
                 static fn (string $value): string => Str::lower($value),
             )
             ->merge(['noopener', 'noreferrer'])
             ->unique()
             ->implode(' ');
         $element->setAttribute('rel', $rel);
     }
 
     private function stripAttributes(DOMElement $element): void
     {
         foreach (iterator_to_array($element->attributes) as $attribute) {
             $element->removeAttribute($attribute->name);
         }
     }
 
     private function unwrap(DOMElement $element): void
     {
         $parent = $element->parentNode;
 
         if ($parent === null) {
             return;
         }
 
         while ($element->firstChild !== null) {
             $parent->insertBefore($element->firstChild, $element);
         }
 
         $parent->removeChild($element);
     }
 
     private function removeNode(DOMElement $element): void
     {
         $parent = $element->parentNode;
 
         if ($parent === null) {
             return;
         }
 
         $parent->removeChild($element);
     }
}
