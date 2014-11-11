<?php
/**
 * Copyright (C) 2011-2014 Graham Breach
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

require_once 'SVGGraphMultiGraph.php';
require_once 'SVGGraphHorizontalBarGraph.php';

class HorizontalStackedBarGraph extends HorizontalBarGraph {

  protected $legend_reverse = false;
  protected $single_axis = true;

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by HorizontalStackedBarGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_height = $this->BarHeight();
    $bspace = max(0, ($this->y_axes[$this->main_y_axis]->Unit() - $bar_height) / 2);
    $bar_style = array();
    $bar = array('height' => $bar_height);

    $bnum = 0;
    $b_start = $this->height - $this->pad_bottom - ($this->bar_space / 2);
    $ccount = count($this->colours);
    $chunk_count = count($this->multi_graph);
    $bars_shown = array_fill(0, $chunk_count, 0);

    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);
      if(!is_null($bar_pos)) {
        $bar['y'] = $bar_pos - $bspace - $bar_height;

        $xpos = $xneg = 0;
        $label_pos_position = $label_neg_position = $this->show_bar_labels ? 
          $this->bar_label_position : '';

        // find greatest -/+ bar
        $max_neg_bar = $max_pos_bar = -1;
        for($j = 0; $j < $chunk_count; ++$j) {
          if($itemlist[$j]->value > 0)
            $max_pos_bar = $j;
          else
            $max_neg_bar = $j;
        }
        for($j = 0; $j < $chunk_count; ++$j) {
          $item = $itemlist[$j];
          $this->SetStroke($bar_style, $item, $j);
          $bar_style['fill'] = $this->GetColour($item, $j % $ccount);

          $this->Bar($item->value, $bar, $item->value >= 0 ? $xpos : $xneg);
          if($item->value < 0)
            $xneg += $item->value;
          else
            $xpos += $item->value;

          if($bar['width'] > 0) {
              ++$bars_shown[$j];

            if($this->show_tooltips)
              $this->SetTooltip($bar, $item, $item->value, null,
                !$this->compat_events && $this->show_bar_labels);
            $rect = $this->Element('rect', $bar, $bar_style);
            if($this->show_bar_labels) {
              if($item->value < 0) {
                $label_neg_position = $this->BarLabelPosition($item, $bar);
                $rect .= $this->BarLabel($item, $bar, $j < $max_neg_bar);
              } else {
                $label_pos_position = $this->BarLabelPosition($item, $bar);
                $rect .= $this->BarLabel($item, $bar, $j < $max_pos_bar);
              }
            }
            $body .= $this->GetLink($item, $k, $rect);
            unset($bar['id']); // clear ID for next generated value
          }
          $this->bar_styles[$j] = $bar_style;
        }
        if($this->show_bar_totals) {
          if($xpos) {
            $body .= $this->BarTotal($xpos, $bar, $label_pos_position == 'above' ?
              $itemlist[$max_pos_bar] : false);
          }
          if($xneg) {
            $body .= $this->BarTotal($xneg, $bar, $label_neg_position == 'above' ?
              $itemlist[$max_neg_bar] : false);
          }
        }
      }
      ++$bnum;
    }
    if(!$this->legend_show_empty) {
      foreach($bars_shown as $j => $bar) {
        if(!$bar)
          $this->bar_styles[$j] = NULL;
      }
    }

    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Overridden to prevent drawing behind higher bars
   * $offset_x should be true for inner bars
   */
  protected function BarLabel(&$item, &$bar, $offset_x = null)
  {
    $content = $item->Data('label');
    if(is_null($content))
      $content = $this->units_before_label . Graph::NumString($item->value) .
        $this->units_label;
    list($text_size) = $this->TextSize($content, $this->bar_label_font_size,
      $this->bar_label_font_adjust, $this->encoding);
    $space = $this->bar_label_space;
    if($offset_x) {

      // bar too small, would be above
      if($bar['width'] < $text_size + 2 * $space)
        return parent::BarLabel($item, $bar, ($bar['width'] + $text_size)/2);

      // option set to above
      if($this->bar_label_position == 'above') {
        $this->bar_label_position = 'top';
        $label = parent::BarLabel($item, $bar);
        $this->bar_label_position = 'above';
        return $label;
      }
    }
    return parent::BarLabel($item, $bar);
  }

  /**
   * Bar total label
   * $label_above is the item that is above the bar
   */
  protected function BarTotal($total, &$bar, $label_above)
  {
    $this->Bar($total, $bar);
    $content = $this->units_before_label . Graph::NumString($total) .
      $this->units_label;
    $font_size = $this->bar_total_font_size;
    $space = $this->bar_total_space;
    $x = $bar['x'] + ($bar['width'] / 2);

    $font_size = $this->bar_total_font_size;
    $y = $bar['y'] + ($bar['height'] + $font_size) / 2 - $font_size / 8;
    $anchor = 'end';

    $top = $bar['x'] + $bar['width'] - $this->bar_total_space;
    $bottom = $bar['x'] + $this->bar_total_space;

    $swap = ($bar['x'] + $bar['width'] <= $this->pad_left + 
      $this->x_axes[$this->main_x_axis]->Zero());
    $x = $swap ? $bottom - $this->bar_total_space * 2 :
      $top + $this->bar_total_space * 2;
    $anchor = $swap ? 'end' : 'start';
    $offset = 0;

    // make space for label
    if($label_above) {
      $lcontent = $label_above->Data('label');
      if(is_null($lcontent))
        $lcontent = $this->units_before_label .
          Graph::NumString($label_above->value) . $this->units_label;
      list($text_size) = $this->TextSize($lcontent, $this->bar_label_font_size,
        $this->bar_label_font_adjust, $this->encoding);
      $offset = $text_size + $this->bar_label_space;
      if($swap)
        $offset = -$offset;
    }

    $text = array(
      'x' => $x + $offset,
      'y' => $y,
      'text-anchor' => $anchor,
      'font-family' => $this->bar_total_font,
      'font-size' => $font_size,
      'fill' => $this->bar_total_colour,
    );
    if($this->bar_total_font_weight != 'normal')
      $text['font-weight'] = $this->bar_total_font_weight;
    return $this->Element('text', $text, NULL, $content);
  }

  /**
   * construct multigraph
   */
  public function Values($values)
  {
    parent::Values($values);
    if(!$this->values->error)
      $this->multi_graph = new MultiGraph($this->values, $this->force_assoc,
        $this->require_integer_keys);
  }

  /**
   * Find the longest data set
   */
  protected function GetHorizontalCount()
  {
    return $this->multi_graph->ItemsCount(-1);
  }

  /**
   * Returns the maximum (stacked) value
   */
  protected function GetMaxValue()
  {
    return $this->multi_graph->GetMaxSumValue();
  }

  /**
   * Returns the minimum (stacked) value
   */
  protected function GetMinValue()
  {
    return $this->multi_graph->GetMinSumValue();
  }
}

