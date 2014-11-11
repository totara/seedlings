<?php
/**
 * Copyright (C) 2012-2014 Graham Breach
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
require_once 'SVGGraphBar3DGraph.php';

class StackedBar3DGraph extends Bar3DGraph {

  protected $legend_reverse = true;
  protected $single_axis = true;

  protected function Draw()
  {
    if($this->log_axis_y)
      throw new Exception('log_axis_y not supported by StackedBar3DGraph');

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_width = $this->block_width = $this->BarWidth();
    $bar = array('width' => $bar_width);

    // make the top parallelogram, set it as a symbol for re-use
    list($this->bx, $this->by) = $this->Project(0, 0, $bar_width);
    $top = $this->BarTop();

    $bspace = max(0, ($this->x_axes[$this->main_x_axis]->Unit() - $bar_width) / 2);
    $bnum = 0;
    $ccount = count($this->colours);
    $chunk_count = count($this->multi_graph);
    $groups = array_fill(0, $chunk_count, '');

    // get the translation for the whole bar
    list($tx, $ty) = $this->Project(0, 0, $bspace);
    $group = array('transform' => "translate($tx,$ty)");
    $bars = '';
    foreach($this->multi_graph as $itemlist) {
      $k = $itemlist[0]->key;
      $bar_pos = $this->GridPosition($k, $bnum);

      if(!is_null($bar_pos)) {
        $bar['x'] = $bspace + $bar_pos;

        // sort the values from bottom to top, assigning position
        $ypos = $yplus = $yminus = 0;
        $chunk_values = array();
        for($j = 0; $j < $chunk_count; ++$j) {
          $item = $itemlist[$j];
          if(!is_null($item->value)) {
            if($item->value < 0) {
              array_unshift($chunk_values, array($j, $item->value, $yminus, $item));
              $yminus += $item->value;
            } else {
              $chunk_values[] = array($j, $item->value, $yplus, $item);
              $yplus += $item->value;
            }
          }
        }

        $bar_count = count($chunk_values);
        $b = 0;
        foreach($chunk_values as $chunk) {
          $j = $chunk[0];
          $value = $chunk[1];
          $item = $chunk[3];
          $colour = $j % $ccount;
          $v = abs($value);
          $t = ++$b == $bar_count ? $top : null;
          $bar_sections = $this->Bar3D($item, $bar, $t, $colour, $chunk[2]);
          $ypos = $ty;
          $group['transform'] = "translate($tx," . $ypos . ")";
          $group['fill'] = $this->GetColour($item, $colour);

          if($this->show_tooltips)
            $this->SetTooltip($group, $item, $value);
          $link = $this->GetLink($item, $k, $bar_sections);
          $this->SetStroke($group, $item, $j, 'round');
          $bars .= $this->Element('g', $group, NULL, $link);
          unset($group['id']); // make sure a new one is generated
          if(!array_key_exists($j, $this->bar_styles))
            $this->bar_styles[$j] = $group;
        }
      }
      ++$bnum;
    }

    $body .= $bars;
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
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
   * Overridden to prevent drawing behind higher bars
   * $offset_y should be true for inner bars
   */
  protected function BarLabel($value, &$bar, $offset_y = null)
  {
    $font_size = $this->bar_label_font_size;
    $space = $this->bar_label_space;
    if($offset_y) {

      // bar too small, would be above
      if($bar['height'] < $font_size + 2 * $space)
        return parent::BarLabel($value, $bar, ($bar['height'] + $font_size)/2);

      // option set to above
      if($this->bar_label_position == 'above') {
        $this->bar_label_position = 'top';
        $label = parent::BarLabel($value, $bar);
        $this->bar_label_position = 'above';
        return $label;
      }
    }
    return parent::BarLabel($value, $bar);
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

