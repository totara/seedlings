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

require_once 'SVGGraphPointGraph.php';

/**
 * LineGraph - joined line, with axes and grid
 */
class LineGraph extends PointGraph {

  protected $require_integer_keys = false;
  protected $line_styles = array();
  protected $fill_styles = array();

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $attr = array('stroke' => $this->stroke_colour, 'fill' => 'none');
    $dash = is_array($this->line_dash) ?
      $this->line_dash[0] : $this->line_dash;
    $stroke_width = is_array($this->line_stroke_width) ?
      $this->line_stroke_width[0] : $this->line_stroke_width;
    if(!empty($dash))
      $attr['stroke-dasharray'] = $dash;
    $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

    $bnum = 0;
    $cmd = 'M';
    $y_axis_pos = $this->height - $this->pad_bottom - 
      $this->y_axes[$this->main_y_axis]->Zero();
    $y_bottom = min($y_axis_pos, $this->height - $this->pad_bottom);

    $path = $fillpath = '';
    foreach($this->values[0] as $item) {
      $x = $this->GridPosition($item->key, $bnum);
      if(!is_null($item->value) && !is_null($x)) {
        $y = $this->GridY($item->value);
        if(!is_null($y)) {

          if(empty($fillpath))
            $fillpath = "M$x {$y_bottom}L";
          $path .= "$cmd$x $y ";
          $fillpath .= "$x $y ";

          // no need to repeat same L command
          $cmd = $cmd == 'M' ? 'L' : '';
          $this->AddMarker($x, $y, $item);
          $last_x = $x;
        }
      }
      ++$bnum;
    }

    $this->line_styles[0] = $attr;
    $attr['d'] = $path;
    $graph_line = $this->Element('path', $attr);

    if($this->fill_under) {
      $attr['fill'] = $this->GetColour(null, 0);
      if($this->fill_opacity < 1.0)
        $attr['fill-opacity'] = $this->fill_opacity;
      $fillpath .= "L{$last_x} {$y_bottom}z";
      $attr['d'] = $fillpath;
      $attr['stroke'] = 'none';
      unset($attr['stroke-dasharray'], $attr['stroke-width']);
      $this->fill_styles[0] = $attr;
      $graph_line = $this->Element('path', $attr) . $graph_line;
    }

    $group = array();
    $this->ClipGrid($group);
    $body .= $this->Element('g', $group, NULL, $graph_line);

    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE);
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }

  /**
   * Line graphs and lines in general require at least two points
   */
  protected function CheckValues()
  {
    parent::CheckValues();

    if($this->values->ItemsCount() <= 1)
      throw new Exception('Not enough values for ' . get_class($this));
  }

  /**
   * Return line and marker for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->line_styles[$set]))
      return '';

    $marker = parent::DrawLegendEntry($set, $x, $y, $w, $h);
    $h1 = $h/2;
    $y += $h1;
    $line = $this->line_styles[$set];
    $line['d'] = "M$x {$y}l$w 0";
    $graph_line = $this->Element('path', $line);
    if($this->fill_under) {
      $fill = $this->fill_styles[$set];
      $fill['d'] = "M$x {$y}l$w 0 0 $h1 -$w 0z";
      $graph_line = $this->Element('path', $fill) . $graph_line;
    }
    return $graph_line . $marker;
  }

}

