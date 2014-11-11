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

require_once 'SVGGraphGridGraph.php';

class BarGraph extends GridGraph {

  protected $bar_styles = array();
  protected $label_centre = TRUE;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);
    $bnum = 0;
    $bar_width = $this->BarWidth();
    $bspace = max(0, ($this->x_axes[$this->main_x_axis]->Unit() - $bar_width) / 2);

    $ccount = count($this->colours);
    foreach($this->values[0] as $item) {

      // assign bar in the loop so it doesn't keep ID
      $bar = array('width' => $bar_width);
      $bar_pos = $this->GridPosition($item->key, $bnum);
      if($this->legend_show_empty || $item->value != 0) {
        $bar_style = array('fill' => $this->GetColour($item, $bnum % $ccount));
        $this->SetStroke($bar_style, $item);
      } else {
        $bar_style = NULL;
      }

      if(!is_null($item->value) && !is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;
        $this->Bar($item->value, $bar);

        if($bar['height'] > 0) {
          if($this->show_tooltips)
            $this->SetTooltip($bar, $item, $item->value, null,
              !$this->compat_events && $this->show_bar_labels);
          $rect = $this->Element('rect', $bar, $bar_style);
          if($this->show_bar_labels)
            $rect .= $this->BarLabel($item, $bar);
          $body .= $this->GetLink($item, $item->key, $rect);
        }
      }
      $this->bar_styles[] = $bar_style;
      ++$bnum;
    }

    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Returns the width of a bar
   */
  protected function BarWidth()
  {
    if(is_numeric($this->bar_width) && $this->bar_width >= 1)
      return $this->bar_width;
    $unit_w = $this->x_axes[$this->main_x_axis]->Unit();
    return $this->bar_space >= $unit_w ? '1' : $unit_w - $this->bar_space;
  }

  /**
   * Fills in the y-position and height of a bar
   * @param number $value bar value
   * @param array  &$bar  bar element array [out]
   * @param number $start bar start value
   * @param number $axis bar Y-axis number
   * @return number unclamped bar position
   */
  protected function Bar($value, &$bar, $start = null, $axis = NULL)
  {
    if($start)
      $value += $start;

    $startpos = is_null($start) ? $this->OriginY($axis) :
      $this->GridY($start, $axis);
    if(is_null($startpos))
      $startpos = $this->OriginY($axis);
    $pos = $this->GridY($value, $axis);
    if(is_null($pos)) {
      $bar['height'] = 0;
    } else {
      $l1 = $this->ClampVertical($startpos);
      $l2 = $this->ClampVertical($pos);
      $bar['y'] = min($l1, $l2);
      $bar['height'] = abs($l1-$l2);
    }
    return $pos;
  }

  /**
   * Returns the position for a bar label
   */
  protected function BarLabelPosition(&$bar)
  {
    $pos = $this->bar_label_position;
    if(empty($pos))
      $pos = 'top';
    $top = $bar['y'] + $this->bar_label_font_size + $this->bar_label_space;
    $bottom = $bar['y'] + $bar['height'] - $this->bar_label_space;
    if($top > $bottom)
      $pos = 'above';
    return $pos;
  }

  /**
   * Text labels in or above the bar
   */
  protected function BarLabel(&$item, &$bar, $offset_y = null)
  {
    $content = $item->Data('label');
    if(is_null($content))
      $content = $this->units_before_label . Graph::NumString($item->value) .
        $this->units_label;
    $font_size = $this->bar_label_font_size;
    $space = $this->bar_label_space;
    $x = $bar['x'] + ($bar['width'] / 2);
    $colour = $this->bar_label_colour;
    $acolour = $this->bar_label_colour_above;

    if(!is_null($offset_y)) {
      $y = $bar['y'] + $offset_y;
    } else {
      $pos = $this->BarLabelPosition($bar);
      $swap = ($bar['y'] >= $this->height - $this->pad_bottom - 
        $this->y_axes[$this->main_y_axis]->Zero());
      switch($pos) {
      case 'above' :
        $y = $swap ? $bar['y'] + $bar['height'] + $font_size + $space :
          $bar['y'] - $space;
        if(!empty($acolour))
          $colour = $acolour;
        break;
      case 'bottom' :
        $y = $bar['y'] + (!$swap ? $bar['height'] - $this->bar_label_space :
          $this->bar_label_font_size + $this->bar_label_space);
        break;
      case 'centre' :
        $y = $bar['y'] + ($bar['height'] + $font_size) / 2;
        break;
      case 'top' :
      default :
        $y = $bar['y'] + ($swap ? $bar['height'] - $this->bar_label_space :
          $this->bar_label_font_size + $this->bar_label_space);
        break;
      }
    }

    $text = array(
      'x' => $x,
      'y' => $y,
      'text-anchor' => 'middle',
      'font-family' => $this->bar_label_font,
      'font-size' => $font_size,
      'fill' => $colour,
    );
    if($this->bar_label_font_weight != 'normal')
      $text['font-weight'] = $this->bar_label_font_weight;
    return $this->Element('text', $text, NULL, $content);
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->bar_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->bar_styles[$set]);
  }

}

