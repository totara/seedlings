Description of dompdf 0.6 library import into Moodle

2013/08/22
REMOVED:
- www/ folder (configuration utility)
- php-font-lib/www/ folder
- dompdf.php
- index.php

ADDED:
+ lib.php Moodle wrapper for dompdf
+ moodle_config.php moodle specific configuration for dompdf


IN LINE CHANGES:
--- old/image_cache.cls.php	2013-08-22 15:26:51.336330602 +1200
+++ new/image_cache.cls.php	2013-08-22 15:29:21.528330303 +1200
@@ -84,7 +84,7 @@
           }
           else {
             set_error_handler("record_warnings");
-            $image = file_get_contents($full_url);
+            $image = totara_dompdf::file_get_contents($full_url);
             restore_error_handler();
           }

--- old/image_frame_decorator.cls.php	2013-08-22 15:23:47.108330970 +1200
+++ new/image_frame_decorator.cls.php	2013-08-22 15:25:16.212330792 +1200
@@ -53,6 +53,7 @@

     if ( Image_Cache::is_broken($this->_image_url) &&
          $alt = $frame->get_node()->getAttribute("alt") ) {
+      $this->_image_msg = '';
       $style = $frame->get_style();
       $style->width  = (4/3)*Font_Metrics::get_text_width($alt, $style->font_family, $style->font_size, $style->word_spacing);
       $style->height = Font_Metrics::get_font_height($style->font_family, $style->font_size);

--- old/image_renderer.cls.php	2013-08-22 15:24:31.300330882 +1200
+++ new/image_renderer.cls.php	2013-08-22 15:25:12.588330799 +1200
@@ -18,6 +18,10 @@
   function render(Frame $frame) {
     // Render background & borders
     $style = $frame->get_style();
+    if (Image_Cache::is_broken($frame->get_image_url())) {
+        $style->width = 32;
+        $style->height = 32;
+    }
     $cb = $frame->get_containing_block();
     list($x, $y, $w, $h) = $frame->get_border_box();

index a1943bc..a9cd5f3 100644
--- a/lib/dompdf/include/cpdf_adapter.cls.php
+++ b/lib/dompdf/include/cpdf_adapter.cls.php
@@ -603,7 +603,7 @@ class CPDF_Adapter implements Canvas {
   function text($x, $y, $text, $font, $size, $color = array(0,0,0), $word_space = 0, $char_space = 0, $angle = 0) {
     $pdf = $this->_pdf;

-    $pdf->setColor($color);
+    $pdf->setColor($color, true);

     $font .= ".afm";
     $pdf->selectFont($font);
