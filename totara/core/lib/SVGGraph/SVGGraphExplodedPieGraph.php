<?php
/**
 * Copyright (C) 2014 Graham Breach
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

require_once 'SVGGraphPieGraph.php';

class ExplodedPieGraph extends PieGraph {

  protected $largest_value;
  protected $smallest_value;

  /**
   * Calculates reduced radius of pie
   */
  protected function Calc()
  {
    parent::Calc();
    $this->explode_amount = min($this->radius_x - 10, $this->radius_y - 10,
      max(2, (int)$this->explode_amount));
    $this->radius_y -= $this->explode_amount;
    $this->radius_x -= $this->explode_amount;
  }

  /**
   * Returns a single slice of pie
   */
  protected function GetSlice($item, $angle_start, $angle_end, $radius_x,
    $radius_y, &$attr, $single_slice)
  {
    if($single_slice)
      return parent::GetSlice($item, $angle_start, $angle_end, $radius_x,
        $radius_y, $attr, $single_slice);

    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $radius_x, $radius_y,
      $x_start, $y_start, $x_end, $y_end);
    $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
    $sweep = ($this->reverse ? 0 : 1);

    // find explosiveness
    list($xo, $yo) = $this->GetExplode($item, $angle_start, $angle_end);
    $xc = $this->x_centre + $xo;
    $yc = $this->y_centre + $yo;
    $x_start += $xo;
    $x_end += $xo;
    $y_start += $yo;
    $y_end += $yo;
    $attr['d'] = "M{$xc},{$yc} L$x_start,$y_start " .
      "A{$radius_x} {$radius_y} 0 $outer,$sweep $x_end,$y_end z";
    return $this->Element('path', $attr);
  }

  /**
   * Returns the x,y offset caused by explosion
   */
  protected function GetExplode($item, $angle_start, $angle_end)
  {
    $range = $this->largest_value - $this->smallest_value;
    switch($this->explode) {
    case 'none' :
      $diff = 0;
      break;
    case 'all' :
      $diff = $range;
      break;
    case 'large' :
      $diff = $item->value - $this->smallest_value;
      break;
    default :
      $diff = $this->largest_value - $item->value;
    }
    $amt = $diff / $range;
    $iamt = $item->Data('explode');
    if(!is_null($iamt))
      $amt = $iamt;
    $explode = $this->explode_amount * $amt;

    $a = $angle_end - $angle_start;
    $a_centre = $angle_start + ($angle_end - $angle_start) / 2;
    $xo = $explode * cos($a_centre);
    $yo = $explode * sin($a_centre);
    if($this->reverse)
      $yo = -$yo;

    return array($xo, $yo);
  }

  /**
   * Returns the x and y position of the label, relative to centre
   */
  protected function GetLabelPosition($item, $a_start, $a_end, $rx, $ry, $text)
  {
    list($xt, $yt) = parent::GetLabelPosition($item, $a_start, $a_end, $rx, $ry,
      $text);

    // get explode wants final angle
    $a_start += $this->s_angle;
    $a_end += $this->s_angle;
    list($xo, $yo) = $this->GetExplode($item, $a_start, $a_end);

    return array($xt + $xo, $yt + $yo);
  }
  
  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    parent::CheckValues();

    $this->largest_value = $this->GetMaxValue();
    $this->smallest_value = $this->largest_value;

    // want smallest non-0 value
    foreach($this->values[0] as $item)
      if($item->value < $this->smallest_value)
        $this->smallest_value = $item->value;
  }

}

