<?php
/**
 * Copyright (C) 2009-2014 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

define('SVGGRAPH_VERSION', 'SVGGraph 2.16');

class SVGGraph {

  private $width = 100;
  private $height = 100;
  private $settings = array();
  public $values = array();
  public $links = NULL;
  public $colours = NULL;

  public function __construct($w, $h, $settings = NULL)
  {
    $this->width = $w;
    $this->height = $h;
    if(is_array($settings))
      $this->settings = $settings;
  }

  public function Values($values)
  {
    if(is_array($values)) 
      $this->values = $values;
    else
      $this->values = func_get_args();
  }
  public function Links($links)
  {
    if(is_array($links)) 
      $this->links = $links;
    else
      $this->links = func_get_args();
  }
  public function Colours($colours)
  {
    $this->colours = $colours;
  }


  /**
   * Instantiate the correct class
   */
  private function Setup($class)
  {
    // load the relevant class file
    if(!class_exists($class))
      include dirname(__FILE__) . '/SVGGraph' . $class . '.php';

    $g = new $class($this->width, $this->height, $this->settings);
    $g->Values($this->values);
    $g->Links($this->links);
    if(!is_null($this->colours))
      $g->colours = $this->colours;
    return $g;
  }

  /**
   * Fetch the content
   */
  public function Fetch($class, $header = TRUE, $defer_js = TRUE)
  {
    $this->g = $this->Setup($class);
    return $this->g->Fetch($header, $defer_js);
  }

  /**
   * Pass in the type of graph to display
   */
  public function Render($class, $header = TRUE, $content_type = TRUE,
    $defer_js = FALSE)
  {
    $this->g = $this->Setup($class);
    return $this->g->Render($header, $content_type, $defer_js);
  }

  public function FetchJavascript()
  {
    if(isset($this->g))
      return $this->g->FetchJavascript(true, true, true);
  }
}

/**
 * Base class for all graph types
 */
abstract class Graph {

  protected $settings = array();
  protected $values = array();
  protected $link_base = '';
  protected $link_target = '_blank';
  protected $links = array();

  protected $gradients = array();
  protected $gradient_map = array();
  protected $pattern_list = NULL;
  protected $defs = array();
  protected $back_matter = '';

  protected $namespaces = array();
  /** @var SVGGraphJavascript */
  protected static $javascript = NULL;
  private static $last_id = 0;
  private static $precision = 5;
  private static $decimal = '.';
  private static $thousands = ',';
  protected $legend_reverse = false;
  protected $force_assoc = false;
  protected $repeated_keys = 'error';
  protected $require_structured = false;
  protected $require_integer_keys = true;
  protected $multi_graph = NULL;

  public function __construct($w, $h, $settings = NULL)
  {
    $this->width = $w;
    $this->height = $h;

    // get settings from ini file that are relevant to this class
    $ini_file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'svggraph.ini';
    if(!file_exists($ini_file))
      $ini_settings = FALSE;
    else
      $ini_settings = parse_ini_file($ini_file, TRUE);
    if($ini_settings === FALSE)
      die("Ini file [{$ini_file}] not found -- exiting");

    $class = get_class($this);
    $hierarchy = array($class);
    while($class = get_parent_class($class))
      array_unshift($hierarchy, $class);

    while(count($hierarchy)) {
      $class = array_shift($hierarchy);
      if(array_key_exists($class, $ini_settings))
        $this->settings = array_merge($this->settings, $ini_settings[$class]);
    }

    if(is_array($settings))
      $this->Settings($settings);

    // set default colours
    $this->colours = array('#11c','#c11','#cc1','#1c1','#c81',
      '#116','#611','#661','#161','#631');
  }


  /**
   * Retrieves properties from the settings array if they are not
   * already available as properties
   */
  public function __get($name)
  {
    $this->{$name} = isset($this->settings[$name]) ? $this->settings[$name] : null;
    return $this->{$name};
  }

  /**
   * Make empty($this->option) more robust
   */
  public function __isset($name)
  {
    return isset($this->settings[$name]);
  }

  /**
   * Sets the options
   */
  public function Settings(&$settings)
  {
    foreach($settings as $key => $value) {
      $this->settings[$key] = $value;
      $this->{$key} = $value;
    }
  }

  /**
   * Sets the graph values
   */
  public function Values($values)
  {
    $new_values = array();
    $v = func_get_args();
    if(count($v) == 1)
      $v = array_shift($v);

    $set_values = true;
    if(is_array($v)) {
      reset($v);
      $first_key = key($v);
      if(!is_null($first_key) && is_array($v[$first_key])) {
        foreach($v as $data_set)
          $new_values[] = $data_set;
        $set_values = false;
      }
    }

    if($set_values)
      $new_values[] = $v;

    if($this->scatter_2d) {
      $this->scatter_2d = false;
      if(empty($this->structure))
        $this->structure = array('key' => 0, 'value' => 1, 'datasets' => true);
    }

    if($this->structured_data || is_array($this->structure)) {
      $this->structured_data = true;
      require_once 'SVGGraphStructuredData.php';
      if(is_array($this->structure)) {
        $this->structure['_before'] = $this->units_before_x;
        $this->structure['_after'] = $this->units_x;
        $this->structure['_encoding'] = $this->encoding;
      }
      $this->values = new SVGGraphStructuredData($new_values,
        $this->force_assoc, $this->structure, $this->repeated_keys,
        $this->require_integer_keys, $this->require_structured);
    } else {
      require_once 'SVGGraphData.php';
      $this->values = new SVGGraphData($new_values, $this->force_assoc);
      if(!$this->values->error && !empty($this->require_structured))
        $this->values->error = get_class($this) . ' requires structured data';
    }
  }

  /**
   * Sets the links from each item
   */
  public function Links()
  {
    $this->links = func_get_args();
  }

  protected function GetMinValue()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMinValue();
    return $this->values->GetMinValue();
  }
  protected function GetMaxValue()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMaxValue();
    return $this->values->GetMaxValue();
  }
  protected function GetMinKey()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMinKey();
    return $this->values->GetMinKey();
  }
  protected function GetMaxKey()
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetMaxKey();
    return $this->values->GetMaxKey();
  }
  protected function GetKey($i)
  {
    if(!is_null($this->multi_graph))
      return $this->multi_graph->GetKey($i);
    return $this->values->GetKey($i);
  }

  /**
   * Draws the selected graph
   */
  public function DrawGraph()
  {
    $canvas_id = $this->NewID();

    $contents = $this->Canvas($canvas_id);
    $contents .= $this->DrawTitle();
    $contents .= $this->Draw();
    $contents .= $this->DrawBackMatter();
    $contents .= $this->DrawLegend();

    // rounded rects might need a clip path
    if($this->back_round && $this->back_round_clip) {
      $group = array('clip-path' => "url(#{$canvas_id})");
      return $this->Element('g', $group, NULL, $contents);
    }
    return $contents;
  }


  /**
   * Adds any markup that goes after the graph
   */
  protected function DrawBackMatter()
  {
    return $this->back_matter;
  }

  /**
   * Draws the legend
   */
  protected function DrawLegend()
  {
    if(empty($this->legend_entries))
      return '';

    // need to find the actual number of entries in the legend
    $entry_count = 0;
    $longest = 0;
    foreach($this->legend_entries as $key => $value) {
      $entry = $this->DrawLegendEntry($key, 0, 0, 20, 20);
      if($entry != '') {
        ++$entry_count;
        if(mb_strlen($value, $this->encoding) > $longest)
          $longest = mb_strlen($value, $this->encoding);
      }
    }
    if(!$entry_count)
      return '';

    $title = '';
    $title_width = $entries_x = 0;
    $text_columns = $entry_columns = array();

    $start_y = $this->legend_padding;
    $w = $this->legend_entry_width;
    $x = 0;
    $entry_height = max($this->legend_font_size, $this->legend_entry_height);
    $text_y_offset = $entry_height / 2 + $this->legend_font_size / 2;

    // make room for title
    if($this->legend_title != '') {
      $title_font = $this->GetFirst($this->legend_title_font,
        $this->legend_font);
      $title_font_size = $this->GetFirst($this->legend_title_font_size,
        $this->legend_font_size);
      $title_font_adjust = $this->GetFirst($this->legend_title_font_adjust,
        $this->legend_font_adjust);
      $title_colour = $this->GetFirst($this->legend_title_colour,
        $this->legend_colour);

      $start_y += $title_font_size + $this->legend_padding;
      $title_width = $this->legend_padding * 2 +
        $title_font_size * $title_font_adjust * 
        mb_strlen($this->legend_title, $this->encoding);
    }

    $columns = max(1, min(ceil($this->legend_columns), $entry_count));
    $per_column = ceil($entry_count / $columns);
    $columns = ceil($entry_count / $per_column);
    $column = 0;

    $text = array('x' => 0);
    $legend_entries = $this->legend_reverse ?
      array_reverse($this->legend_entries, true) : $this->legend_entries;

    $column_entry = 0;
    $y = $start_y;
    foreach($legend_entries as $key => $value) {
      if(!empty($value)) {
        $entry = $this->DrawLegendEntry($key, $x, $y, $w, $entry_height);
        if(!empty($entry)) {
          $text['y'] = $y + $text_y_offset;
          if(isset($text_columns[$column]))
            $text_columns[$column] .= $this->Element('text', $text, NULL, $value);
          else
            $text_columns[$column] = $this->Element('text', $text, NULL, $value);
          if(isset($entry_columns[$column]))
            $entry_columns[$column] .= $entry;
          else
            $entry_columns[$column] = $entry;
          $y += $entry_height + $this->legend_padding;

          if(++$column_entry == $per_column) {
            $column_entry = 0;
            $y = $start_y;
            ++$column;
          }
        }
      }
    }
    // if there's nothing to go in the legend, stop now
    if(empty($entry_columns))
      return '';

    $text_space = $longest * $this->legend_font_size * 
      $this->legend_font_adjust;
    if($this->legend_text_side == 'left') {
      $text_x_offset = $text_space + $this->legend_padding;
      $entries_x_offset = $text_space + $this->legend_padding * 2;
    } else {
      $text_x_offset = $w + $this->legend_padding * 2;
      $entries_x_offset = $this->legend_padding;
    }
    $longest_width = $this->legend_padding * (2 * $columns + 1) +
      ($this->legend_entry_width + $text_space) * $columns;
    $column_width = $this->legend_padding * 2 + $this->legend_entry_width +
      $text_space;
    $width = max($title_width, $longest_width);
    $height = $start_y + $per_column * ($entry_height + $this->legend_padding);

    // centre the entries if the title makes the box bigger
    if($width > $longest_width) {
      $offset = ($width - $longest_width) / 2;
      $entries_x_offset += $offset;
      $text_x_offset += $offset;
    }

    $text_group = array('transform' => "translate($text_x_offset,0)");
    if($this->legend_text_side == 'left')
      $text_group['text-anchor'] = 'end';
    $entries_group = array('transform' => "translate($entries_x_offset,0)");

    $parts = '';
    foreach($entry_columns as $col) {
      $parts .= $this->Element('g', $entries_group, null, $col);
      $entries_x_offset += $column_width;
      $entries_group['transform'] = "translate($entries_x_offset,0)";
    }
    foreach($text_columns as $col) {
      $parts .= $this->Element('g', $text_group, null, $col);
      $text_x_offset += $column_width;
      $text_group['transform'] = "translate($text_x_offset,0)";
    }

    // create box and title
    $box = array(
      'fill' => $this->ParseColour($this->legend_back_colour),
      'width' => $width,
      'height' => $height,
    );
    if($this->legend_round > 0)
      $box['rx'] = $box['ry'] = $this->legend_round;
    if($this->legend_stroke_width) {
      $box['stroke-width'] = $this->legend_stroke_width;
      $box['stroke'] = $this->legend_stroke_colour;
    }
    $rect = $this->Element('rect', $box);
    if($this->legend_title != '') {
      $text['x'] = $width / 2;
      $text['y'] = $this->legend_padding + $title_font_size;
      $text['text-anchor'] = 'middle';
      if($title_font != $this->legend_font)
        $text['font-family'] = $title_font;
      if($title_font_size != $this->legend_font_size)
        $text['font-size'] = $title_font_size;
      if($this->legend_title_font_weight != $this->legend_font_weight)
        $text['font-weight'] = $this->legend_title_font_weight;
      if($title_colour != $this->legend_colour)
        $text['fill'] = $title_colour;
      $title = $this->Element('text', $text, NULL, $this->legend_title);
    }

    // create group to contain whole legend
    list($left, $top) = $this->ParsePosition($this->legend_position,
      $width, $height);
    $group = array(
      'font-family' => $this->legend_font,
      'font-size' => $this->legend_font_size,
      'fill' => $this->legend_colour,
      'transform' => "translate($left,$top)",
    );
    if($this->legend_font_weight != 'normal')
      $group['font-weight'] = $this->legend_font_weight;

    // add shadow if not completely transparent
    if($this->legend_shadow_opacity > 0) {
      $box['x'] = $box['y'] = 2 + ($this->legend_stroke_width / 2);
      $box['fill'] = '#000';
      $box['opacity'] = $this->legend_shadow_opacity;
      unset($box['stroke'], $box['stroke-width']);
      $rect = $this->Element('rect', $box) . $rect;
    }

    if($this->legend_autohide)
      $this->AutoHide($group);
    if($this->legend_draggable)
      $this->SetDraggable($group);
    return $this->Element('g', $group, NULL, $rect . $title . $parts);
  }

  /**
   * Parses a position string, returning x and y coordinates
   */
  protected function ParsePosition($pos, $w = 0, $h = 0, $pad = 0)
  {
    $inner = true;
    $parts = preg_split('/\s+/', $pos);
    if(count($parts)) {
      // if 'outer' is found after 'inner', it takes precedence
      $parts = array_reverse($parts);
      $inner_at = array_search('inner', $parts);
      $outer_at = array_search('outer', $parts);

      if($outer_at !== false && ($inner_at === false || $inner_at < $outer_at))
        $inner = false;
    }
  
    if($inner) {
      $t = $this->pad_top;
      $l = $this->pad_left;
      $b = $this->height - $this->pad_bottom;
      $r = $this->width - $this->pad_right;
      // make sure it fits to keep RelativePosition happy
      if($w > $r - $l) $w = $r - $l;
      if($h > $b - $t) $h = $b - $t;
    } else {
      $t = $l = 0;
      $b = $this->height;
      $r = $this->width;
    }

    // ParsePosition is always inside canvas or graph
    $pos .= ' inside';
    return Graph::RelativePosition($pos, $t, $l, $b, $r, $w, $h, $pad);
  }

  /**
   * Returns the [x,y] position that is $pos relative to the
   * top, left, bottom and right. When $text is true, returns
   * [x,y,align right]
   */
  public static function RelativePosition($pos, $top, $left,
    $bottom, $right, $width, $height, $pad, $text = false)
  {
    $offset_x = $offset_y = 0;
    $inside = $atop = $aleft = true;
    $parts = preg_split('/\s+/', $pos);
    while(count($parts)) {
      $part = array_shift($parts);
      switch($part) {
      case 'outside' : $inside = false;
        break;
      case 'inside' : $inside = true;
        break;
      case 'top' : $atop = true;
        break;
      case 'bottom' : $atop = false;
        break;
      case 'left' : $aleft = true;
        break;
      case 'right' : $aleft = false;
        break;
      default:
        if(is_numeric($part)) {
          $offset_x = $part;
          if(count($parts) && is_numeric($parts[0]))
            $offset_y = array_shift($parts);
        }
      }
    }
    $edge = $atop ? $top : $bottom;
    $fit = $inside && $bottom - $top >= $pad + $height;

    // padding +ve if both fitting in at top, or outside at bottom
    $distance = ($atop == $fit) ? $pad : -($pad + $height);
    $y = $edge + $distance;

    $edge = $aleft ? $left : $right;
    $fit = $inside && $right - $left >= $pad + $width;
    $distance = ($aleft == $fit) ? $pad :
      ($text ? -$pad : -($pad + $width));
    $x = $edge + $distance;

    $y += $offset_y;
    $x += $offset_x;
  
    // third return value is whether text should be right-aligned
    $text_right = $text && ($aleft != $fit);
    return array($x, $y, $text_right);
  }


  /**
   * Subclasses must draw the entry, if they can
   */
  protected function DrawLegendEntry($key, $x, $y, $w, $h)
  {
    return '';
  }

  /**
   * Draws the graph title, if there is one
   */
  protected function DrawTitle()
  {
    // graph_title is available for all graph types
    if(mb_strlen($this->graph_title, $this->encoding) <= 0)
      return '';

    $pos = $this->graph_title_position;
    $text = array(
      'font-size' => $this->graph_title_font_size,
      'font-family' => $this->graph_title_font,
      'font-weight' => $this->graph_title_font_weight,
      'text-anchor' => 'middle',
      'fill' => $this->graph_title_colour
    );
    $lines = $this->CountLines($this->graph_title);
    $title_space = $this->graph_title_font_size * $lines +
      $this->graph_title_space;
    if($pos != 'top' && $pos != 'bottom' && $pos != 'left' && $pos != 'right')
      $pos = 'top';
    $pad_side = 'pad_' . $pos;

    // ensure outside padding is at least the title space
    if($this->{$pad_side} < $this->graph_title_space)
      $this->{$pad_side} = $this->graph_title_space;

    if($pos == 'left') {
      $text['x'] = $this->pad_left + $this->graph_title_font_size;
      $text['y'] = $this->height / 2;
      $text['transform'] = "rotate(270,$text[x],$text[y])";
    } elseif($pos == 'right') {
      $text['x'] = $this->width - $this->pad_right -
        $this->graph_title_font_size;
      $text['y'] = $this->height / 2;
      $text['transform'] = "rotate(90,$text[x],$text[y])";
    } elseif($pos == 'bottom') {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->height - $this->pad_bottom -
        $this->graph_title_font_size * ($lines-1);
    } else {
      $text['x'] = $this->width / 2;
      $text['y'] = $this->pad_top + $this->graph_title_font_size;
    }
    // increase padding by size of text
    $this->{$pad_side} += $title_space;

    // the Text function will break it into lines
    return $this->Text($this->graph_title, $this->graph_title_font_size,
      $text);
  }


  /**
   * This should be overridden by subclass!
   */
  abstract protected function Draw();

  /**
   * Displays the background image
   */
  protected function BackgroundImage()
  {
    if(!$this->back_image)
      return '';
    $image = array(
      'width' => $this->back_image_width,
      'height' => $this->back_image_height,
      'x' => $this->back_image_left,
      'y' => $this->back_image_top,
      'xlink:href' => $this->back_image,
      'preserveAspectRatio' => 
        ($this->back_image_mode == 'stretch' ? 'none' : 'xMinYMin')
    );
    $style = array();
    if($this->back_image_opacity)
      $style['opacity'] = $this->back_image_opacity;

    $contents = '';
    if($this->back_image_mode == 'tile') {
      $image['x'] = 0; $image['y'] = 0;
      $im = $this->Element('image', $image, $style);
      $pattern = array(
        'id' => $this->NewID(),
        'width' => $this->back_image_width,
        'height' => $this->back_image_height,
        'x' => $this->back_image_left,
        'y' => $this->back_image_top,
        'patternUnits' => 'userSpaceOnUse'
      );
      // tiled image becomes a pattern to replace background colour
      $this->defs[] = $this->Element('pattern', $pattern, NULL, $im);
      $this->back_colour = "url(#{$pattern['id']})";
    } else {
      $im = $this->Element('image', $image, $style);
      $contents .= $im;
    }
    return $contents;
  }

  /**
   * Displays the background
   */
  protected function Canvas($id)
  {
    $bg = $this->BackgroundImage();
    $colour = $this->ParseColour($this->back_colour);
    $opacity = 1;
    if(strpos($colour, ':') !== FALSE)
      list($colour, $opacity) = explode(':', $colour);

    $canvas = array(
      'width' => '100%', 'height' => '100%',
      'fill' => $colour,
      'stroke-width' => 0
    );
    if($opacity < 1)
      if($opacity <= 0)
        $canvas['fill'] = 'none';
      else
        $canvas['opacity'] = $opacity;

    if($this->back_round)
      $canvas['rx'] = $canvas['ry'] = $this->back_round;
    if($bg == '' && $this->back_stroke_width) {
      $canvas['stroke-width'] = $this->back_stroke_width;
      $canvas['stroke'] = $this->back_stroke_colour;
    }
    $c_el = $this->Element('rect', $canvas);

    // create a clip path for rounded rectangle
    if($this->back_round)
      $this->defs[] = $this->Element('clipPath', array('id' => $id),
        NULL, $c_el);
    // if the background image is an element, insert it between the background
    // colour and border rect
    if($bg != '') {
      $c_el .= $bg;
      if($this->back_stroke_width) {
        $canvas['stroke-width'] = $this->back_stroke_width;
        $canvas['stroke'] = $this->back_stroke_colour;
        $canvas['fill'] = 'none';
        $c_el .= $this->Element('rect', $canvas);
      }
    }
    return $c_el;
  }

  /**
   * Fits text to a box - text will be bottom-aligned
   */
  protected function TextFit($text, $x, $y, $w, $h, $attribs = NULL,
    $styles = NULL)
  {
    $pos = array('onload' => "textFit(evt,$x,$y,$w,$h)");
    if(is_array($attribs))
      $pos = array_merge($attribs, $pos);
    $txt = $this->Element('text', $pos, $styles, $text);

    /** Uncomment to see the box
    $rect = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h,
      'fill' => 'none', 'stroke' => 'black');
    $txt .= $this->Element('rect', $rect);
    **/
    $this->AddFunction('textFit');
    return $txt;
  }

  /**
   * Returns a text element, with tspans for subsequent lines
   */
  protected function Text($text, $line_spacing, $attribs, $styles = NULL)
  {
    $lines = explode("\n", $text);
    $content = array_shift($lines);
    $content = ($content == '' ? ' ' : htmlspecialchars($content));

    foreach($lines as $line) {
      // blank tspan elements collapse to nothing, so insert a space
      $line = ($line == '' ? ' ' : htmlspecialchars($line));
      $content .= $this->Element('tspan',
        array('x' => $attribs['x'], 'dy' => $line_spacing),
        NULL, $line);
    }
    return $this->Element('text', $attribs, $styles, $content); 
  }

  /**
   * Returns [width,height] of text 
   * $text = string OR text length
   */
  protected static function TextSize($text, $font_size, $font_adjust, $encoding,
    $angle = 0, $line_spacing = 0)
  {
    $height = $font_size;
    if(is_int($text)) {
      $len = $text;
    } else {
      // replace all entities with an underscore
      $text = preg_replace('/&[^;]+;/', '_', $text);
      if($line_spacing > 0) {
        $len = 0;
        $lines = explode("\n", $text);
        foreach($lines as $l)
          if(mb_strlen($l, $encoding) > $len)
            $len = mb_strlen($l, $encoding);
        $height += $line_spacing * (count($lines) - 1);
      } else {
        $len = mb_strlen($text, $encoding);
      }
    }
    $width = $len * $font_size * $font_adjust;
    if($angle % 180 != 0) {
      if($angle % 90 == 0) {
        $w = $height;
        $height = $width;
        $width = $w;
      } else {
        $a = deg2rad($angle);
        $sa = abs(sin($a));
        $ca = abs(cos($a));
        $w = $ca * $width + $sa * $height;
        $h = $sa * $width + $ca * $height;
        $width = $w;
        $height = $h;
      }
    }
    return array($width, $height);
  }

  /**
   * Returns the number of lines in a string
   */
  protected static function CountLines($text)
  {
    $c = 1;
    $pos = 0;
    while(($pos = strpos($text, "\n", $pos)) !== FALSE) {
      ++$c;
      ++$pos;
    }
    return $c;
  }

  /**
   * Displays readable (hopefully) error message
   */
  protected function ErrorText($error)
  {
    $text = array('x' => $this->pad_left, 'y' => $this->height - 3);
    $style = array(
      'font-family' => 'monospace',
      'font-size' => '11px',
      'font-weight' => 'bold',
    );
    
    $e = $this->ContrastText($text['x'], $text['y'], $error, 'blue',
      'white', $style);
    return $e;
  }

  /**
   * Displays high-contrast text
   */
  protected function ContrastText($x, $y, $text, $fcolour = 'black',
    $bcolour = 'white', $properties = NULL, $styles = NULL)
  {
    $props = array('transform' => 'translate(' . $x . ',' . $y . ')',
      'fill' => $fcolour);
    if(is_array($properties))
      $props = array_merge($properties, $props);

    $bg = $this->Element('text',
      array('stroke-width' => '2px', 'stroke' => $bcolour), NULL, $text);
    $fg = $this->Element('text', NULL, NULL, $text);
    return $this->Element('g', $props, $styles, $bg . $fg);
  }
 
  /**
   * Builds an element
   */
  public function Element($name, $attribs = NULL, $styles = NULL,
    $content = NULL)
  {
    // these properties require units to work well
    $require_units = array('stroke-width' => 1, 'stroke-dashoffset' => 1,
      'font-size' => 1, 'baseline-shift' => 1, 'kerning' => 1, 
      'letter-spacing' =>1, 'word-spacing' => 1);

    if($this->namespace && strpos($name, ':') === FALSE)
      $name = 'svg:' . $name;
    $element = '<' . $name;
    if(is_array($attribs))
      foreach($attribs as $attr => $val) {

        // if units required, add px
        if(is_numeric($val)) {
          if(isset($require_units[$attr]))
            $val .= 'px';
        } else {
          $val = htmlspecialchars($val);
        }
        $element .= ' ' . $attr . '="' . $val . '"';
      }

    if(is_array($styles)) {
      $element .= ' style="';
      foreach($styles as $attr => $val) {
        // check units again
        if(is_numeric($val)) {
          if(isset($require_units[$attr]))
            $val .= 'px';
        } else {
          $val = htmlspecialchars($val);
        }
        $element .= $attr . ':' . $val . ';';
      }
      $element .= '"';
    }

    if(is_null($content))
      $element .= "/>\n";
    else
      $element .= '>' . $content . '</' . $name . ">\n";

    return $element;
  }

  /**
   * Returns a link URL or NULL if none
   */
  protected function GetLinkURL($item, $key, $row = 0)
  {
    $link = is_null($item) ? null : $item->Data('link');
    if(is_null($link) && is_array($this->links[$row]) &&
      isset($this->links[$row][$key])) {
      $link = $this->links[$row][$key];
    }

    // check for absolute links
    if(!is_null($link) && strpos($link,'//') === FALSE)
      $link = $this->link_base . $link;

    return $link;
  }

  /**
   * Retrieves a link
   */
  protected function GetLink($item, $key, $content, $row = 0)
  {
    $link = $this->GetLinkURL($item, $key, $row);
    if(is_null($link))
      return $content;

    $link_attr = array('xlink:href' => $link, 'target' => $this->link_target);
    return $this->Element('a', $link_attr, NULL, $content);
  }

  /**
   * Returns a colour reference
   */
  protected function GetColour($item, $key, $no_gradient = FALSE,
    $allow_pattern = FALSE)
  {
    $colour = 'none';
    $icolour = is_null($item) ? null : $item->Data('colour');
    if(!is_null($icolour)) {
      $colour = $icolour;
      $key = null; // don't reuse existing colours
    } elseif(isset($this->colours[$key])) {
      $colour = $this->colours[$key];
    }
    return $this->ParseColour($colour, $key, $no_gradient, $allow_pattern);
  }

  /**
   * Converts a SVGGraph colour/gradient/pattern to a SVG attribute
   */
  public function ParseColour($colour, $key = NULL, $no_gradient = FALSE,
    $allow_pattern = FALSE)
  {
    if(is_array($colour)) {
      if(!isset($colour['pattern']))
        $allow_pattern = FALSE;
      if($no_gradient && !$allow_pattern) {
        $colour = $this->SolidColour($colour);
      } elseif(isset($colour['pattern'])) {
        $pattern_id = $this->AddPattern($colour);
        $colour = "url(#{$pattern_id})";
      } else {
        $err = array_diff_key($colour, array_keys(array_keys($colour)));
        if($err)
          throw new Exception('Malformed gradient/pattern');
        $gradient_id = $this->AddGradient($colour, $key);
        $colour = "url(#{$gradient_id})";
      }
    }
    return $colour;
  }

  /**
   * Returns the solid colour from a gradient
   */
  protected static function SolidColour($c)
  {
    if(is_array($c)) {
      // grab the first colour in the array, discarding opacity
      $c = $c[0];
      $colon = strpos($c, ':');
      if($colon)
        $c = substr($c, 0, $colon);
    }
    return $c;
  }

  /**
   * Returns the first non-empty argument
   */
  protected static function GetFirst()
  {
    $opts = func_get_args();
    foreach($opts as $opt)
      if(!empty($opt) || $opt === 0)
        return $opt;
  }

  /**
   * Returns an option from array, or non-array option
   */
  protected static function ArrayOption($o, $i)
  {
    return is_array($o) ? $o[$i % count($o)] : $o;
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    if($this->values->error)
      throw new Exception($this->values->error);
  }

  /**
   * Sets the stroke options for an element
   */
  protected function SetStroke(&$attr, &$item, $set = 0, $line_join = null)
  {
    $stroke_width = $this->GetFromItemOrMember('stroke_width', $set, $item);
    if($stroke_width > 0) {
      $attr['stroke'] = $this->GetFromItemOrMember('stroke_colour', $set, $item);
      $attr['stroke-width'] = $stroke_width;
      if(!is_null($line_join))
        $attr['stroke-linejoin'] = $line_join;
      else
        unset($attr['stroke-linejoin']);

      $dash = $this->GetFromItemOrMember('stroke_dash', $set, $item);
      if(!empty($dash))
        $attr['stroke-dasharray'] = $dash;
      else
        unset($attr['stroke-dasharray']);
    }
  }

  /**
   * Creates a new ID for an element
   */
  public function NewID()
  {
    return $this->id_prefix . 'e' . base_convert(++Graph::$last_id, 10, 36);
  }

  /**
   * Adds markup to be inserted between graph and legend
   */
  public function AddBackMatter($fragment)
  {
    $this->back_matter .= $fragment;
  }

  /**
   * Loads the Javascript class
   */
  private function LoadJavascript()
  {
    if(!isset(Graph::$javascript)) {
      include_once dirname(__FILE__) . '/SVGGraphJavascript.php';
      Graph::$javascript = new SVGGraphJavascript($this->settings, $this);
    }
  }

  /**
   * Adds one or more javascript functions
   */
  protected function AddFunction($name)
  {
    $this->LoadJavascript();
    $fns = func_get_args();
    foreach($fns as $fn)
      Graph::$javascript->AddFunction($fn);
  }

  /**
   * Adds a Javascript variable
   * - use $value:$more for assoc
   * - use null:$more for array
   */
  public function InsertVariable($var, $value, $more = NULL)
  {
    $this->LoadJavascript();
    Graph::$javascript->InsertVariable($var, $value, $more);
  }

  /**
   * Insert a comment into the Javascript section - handy for debugging!
   */
  public function InsertComment($details)
  {
    $this->LoadJavascript();
    Graph::$javascript->InsertComment($details);
  }

  /**
   * Adds a pattern, returning the element ID
   */
  public function AddPattern($pattern)
  {
    if(is_null($this->pattern_list)) {
      require_once 'SVGGraphPattern.php';
      $this->pattern_list = new SVGGraphPatternList($this);
    }
    return $this->pattern_list->Add($pattern);
  }

  /**
   * Adds a gradient to the list, returning the element ID for use in url
   */
  public function AddGradient($colours, $key = null)
  {
    if(is_null($key) || !isset($this->gradients[$key])) {

      // find out if this gradient already stored
      $hash = serialize($colours);
      if(isset($this->gradient_map[$hash]))
        return $this->gradient_map[$hash];

      $id = $this->NewID();
      if(is_null($key))
        $key = $id;
      $this->gradients[$key] = array(
        'id' => $id,
        'colours' => $colours
      );
      $this->gradient_map[$hash] = $id;
      return $id;
    }
    return $this->gradients[$key]['id'];
  }

  /**
   * Creates a linear gradient element
   */
  private function MakeLinearGradient($key)
  {
    $stops = '';
    $direction = 'v';
    $colours = $this->gradients[$key]['colours'];
    $id = $this->gradients[$key]['id'];

    if(in_array($colours[count($colours)-1], array('h','v')))
      $direction = array_pop($colours);
    $x2 = $direction == 'v' ? 0 : '100%';
    $y2 = $direction == 'h' ? 0 : '100%';
    $gradient = array('id' => $id, 'x1' => 0, 'x2' => $x2,
      'y1' => 0, 'y2' => $y2);

    $col_mul = 100 / (count($colours) - 1);
    foreach($colours as $pos => $colour) {
      $opacity = null;
      if(strpos($colour, ':') !== FALSE)
        list($colour, $opacity) = explode(':', $colour);
      $stop = array(
        'offset' => round($pos * $col_mul) . '%',
        'stop-color' => $colour
      );
      if(is_numeric($opacity))
        $stop['stop-opacity'] = $opacity;
      $stops .= $this->Element('stop', $stop);
    }

    return $this->Element('linearGradient', $gradient, NULL, $stops);
  }

  /**
   * Adds an inline event handler to an element's array
   */
  protected function AddEventHandler(&$array, $evt, $code)
  {
    $this->LoadJavascript();
    Graph::$javascript->AddEventHandler($array, $evt, $code);
  }

  /**
   * Makes an item draggable
   */
  protected function SetDraggable(&$element)
  {
    $this->LoadJavascript();
    Graph::$javascript->SetDraggable($element);
  }

  /**
   * Makes something auto-hide
   */
  protected function AutoHide(&$element)
  {
    $this->LoadJavascript();
    Graph::$javascript->AutoHide($element);
  }


  /**
   * Default tooltip contents are key and value, or whatever
   * $key is if $value is not set
   */
  protected function SetTooltip(&$element, &$item, $key, $value = NULL,
    $duplicate = FALSE)
  {
    // use structured data tooltips if specified
    if(is_array($this->structure) && isset($this->structure['tooltip'])) {
      $text = $item->Data('tooltip');
      if(is_null($text))
        return;
    } else {
      // use a sprintf format for the tooltip
      $format = '%s, %s';
      if(is_null($value)) {
        $value = $key;
        $format = '%2$s'; // value only
      }
      $k = is_numeric($key) ? $this->units_before_tooltip_key . 
        Graph::NumString($key) . $this->units_tooltip_key : $key;
      $v = is_numeric($value) ? $this->units_before_tooltip . 
        Graph::NumString($value) . $this->units_tooltip : $value;
      $text = sprintf($format, $k, $v);
    }
    Graph::$javascript->SetTooltip($element, $text, $duplicate);
  }

  /**
   * Sets the fader for an element
   */
  protected function SetFader(&$element, $in, $out, $target = NULL,
    $duplicate = FALSE)
  {
    $this->LoadJavascript();
    Graph::$javascript->SetFader($element, $in, $out, $target, $duplicate);
  }

  /**
   * Add an overlaid copy of an element, with opacity of 0
   * $from and $to are the IDs of the source and destination
   */
  protected function AddOverlay($from, $to)
  {
    $this->LoadJavascript();
    Graph::$javascript->AddOverlay($from, $to);
  }

  /**
   * Returns the SVG document
   */
  public function Fetch($header = TRUE, $defer_javascript = TRUE)
  {
    $content = '';
    if($header) {
      $content .= '<?xml version="1.0"';
      // encoding comes before standalone
      if(strlen($this->encoding) > 0)
        $content .= " encoding=\"{$this->encoding}\"";
      // '>' is with \n so as not to confuse syntax highlighting
      $content .= " standalone=\"no\"?" . ">\n";
      if($this->doctype)
        $content .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ' .
        '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
    }

    // set the precision - PHP default is 14 digits!
    Graph::$precision = $this->settings['precision'];
    $old_precision = ini_set('precision', Graph::$precision);
    // set decimal and thousands for NumString
    Graph::$decimal = $this->settings['decimal'];
    Graph::$thousands = $this->settings['thousands'];

    // display title and description if available
    $heading = '';
    if($this->title)
      $heading .= $this->Element('title', NULL, NULL, $this->title);
    if($this->description)
      $heading .= $this->Element('desc', NULL, NULL, $this->description);

    try {
      $this->CheckValues($this->values);

      if($this->show_tooltips)
        $this->LoadJavascript();

      // get the body content from the subclass
      $body = $this->DrawGraph();
    } catch(Exception $e) {
      $err = $e->getMessage();
      if($this->exception_details)
        $err .= " [" . basename($e->getFile()) . ' #' . $e->getLine() . ']';
      $body = $this->ErrorText($err);
    }

    $svg = array(
      'width' => $this->width, 'height' => $this->height,
      'viewBox' => "0 0 $this->width $this->height",
      'version' => '1.1', 
      'xmlns:xlink' => 'http://www.w3.org/1999/xlink'
    );
    if ($this->auto_fit_parent) {
        $svg['width'] = $svg['height'] = '100%';
    }
    if ($this->preserve_aspect_ratio) {
        $svg['preserveAspectRatio'] = $this->preserve_aspect_ratio;
    }

    if(!$defer_javascript) {
      $js = $this->FetchJavascript();
      if($js != '') {
        $heading .= $js;
        $onload = Graph::$javascript->GetOnload();
        if($onload != '')
          $svg['onload'] = $onload;
      }
    }

    // insert any gradients that are used
    foreach($this->gradients as $key => $gradient)
      $this->defs[] = $this->MakeLinearGradient($key);
    // and any patterns
    if(!is_null($this->pattern_list))
      $this->pattern_list->MakePatterns($this->defs);

    // show defs and body content
    if(count($this->defs))
      $heading .= $this->Element('defs', NULL, NULL, implode('', $this->defs));
    if($this->namespace)
      $svg['xmlns:svg'] = "http://www.w3.org/2000/svg";
    else
      $svg['xmlns'] = "http://www.w3.org/2000/svg";

    // add any extra namespaces
    foreach($this->namespaces as $ns => $url)
      $svg['xmlns:' . $ns] = $url;

    // display version string
    if($this->show_version) {
      $text = array('x' => $this->pad_left, 'y' => $this->height - 3);
      $style = array(
        'font-family' => 'monospace', 'font-size' => '12px',
        'font-weight' => 'bold',
      );
      $body .= $this->ContrastText($text['x'], $text['y'], SVGGRAPH_VERSION,
        'blue', 'white', $style);
    }

    $content .= $this->Element('svg', $svg, NULL, $heading . $body);
    // replace PHP's precision
    ini_set('precision', $old_precision);

    if($this->minify)
      $content = preg_replace('/\>\s+\</', '><', $content);
    return $content;
  }

  /**
   * Renders the SVG document
   */
  public function Render($header = TRUE, $content_type = TRUE, 
    $defer_javascript = FALSE)
  {
    $mime_header = 'Content-type: image/svg+xml; charset=UTF-8';
    try {
      $content = $this->Fetch($header, $defer_javascript);
      if($content_type)
        header($mime_header);
      echo $content;
    } catch(Exception $e) {
      if($content_type)
        header($mime_header);
      $this->ErrorText($e);
    }
  }

  /**
   * When using the defer_javascript option, this returns the
   * Javascript block
   */
  public function FetchJavascript($onload_immediate = TRUE, $cdata_wrap = TRUE,
    $no_namespace = TRUE)
  {
    $js = '';
    if(isset(Graph::$javascript)) {
      $variables = Graph::$javascript->GetVariables();
      $functions = Graph::$javascript->GetFunctions();
      $onload = Graph::$javascript->GetOnload();

      if($variables != '' || $functions != '') {
        if($onload_immediate)
          $functions .= "\n" . "setTimeout(function(){{$onload}},20);";
        $script_attr = array('type' => 'application/ecmascript');
          $script = "$variables\n$functions\n";
        if(!empty($this->minify_js) && function_exists($this->minify_js))
          $script = call_user_func($this->minify_js, $script);
        if($cdata_wrap)
          $script = "// <![CDATA[\n$script\n// ]]>";
        $namespace = $this->namespace;
        if($no_namespace)
          $this->namespace = false;
        $js = $this->Element('script', $script_attr, NULL, $script);
        if($no_namespace)
          $this->namespace = $namespace;
      }
    }
    return $js;
  }

  /**
   * Returns a value from the $item, or the member % set
   */
  protected function GetFromItemOrMember($member, $set, &$item, $ikey = null)
  {
    $value = is_null($item) ? null : $item->Data(is_null($ikey) ? $member : $ikey);
    if(is_null($value))
      $value = is_array($this->{$member}) ?
        $this->{$member}[$set % count($this->{$member})] :
        $this->{$member};
    return $value;
  }

  /**
   * Converts number to string
   */
  public static function NumString($n, $decimals = null, $precision = null)
  {
    if(is_int($n)) {
      $d = is_null($decimals) ? 0 : $decimals;
    } else {

      if(is_null($precision))
        $precision = Graph::$precision;

      // if there are too many zeroes before other digits, round to 0
      $e = floor(log(abs($n), 10));
      if(-$e > $precision)
        $n = 0;

      // subtract number of digits before decimal point from precision
      // for precision-based decimals
      $d = is_null($decimals) ? $precision - ($e > 0 ? $e : 0) : $decimals;
    }
    $s = number_format($n, $d, Graph::$decimal, Graph::$thousands);

    if(is_null($decimals) && $d && strpos($s, Graph::$decimal) !== false) {
      list($a, $b) = explode(Graph::$decimal, $s);
      $b1 = rtrim($b, '0');
      if($b1 != '')
        return $a . Graph::$decimal . $b1;
      return $a;
    }
    return $s;
  }

  /**
   * Returns the minimum value in the array, ignoring NULLs
   */
  public static function min(&$a)
  {
    $min = null;
    reset($a);
    while(list(,$v) = each($a)) {
      if(!is_null($v) && (is_null($min) || $v < $min))
        $min = $v;
    }
    return $min;
  }
}

