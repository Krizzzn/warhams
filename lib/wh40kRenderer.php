<?php

require_once('Renderer.php');

class wh40kRenderer extends Renderer {
    protected function renderUnit($unit, $xOffset, $yOffset) {
        // half page, landscape:
        $this->maxX = 144 * 5.5;
        $this->maxY = 144 * 8.5;

        $this->currentX = $xOffset;
        $this->currentY = $yOffset;

        // more stuff here

        # background (we crop and cover the corners later):
        $draw = $this->getDraw();
        $draw->setFillColor('#EEEEEE');
        $draw->rectangle(($this->margin + $this->currentX), 70, $this->maxX - $this->margin + 
                         $this->currentX, $this->maxY - $this->margin);
        $this->image->drawImage($draw);

        $this->renderHeader($unit);

        # model statlines:
        if(count($unit['model_stat'])) {
            $this->renderTable($unit['model_stat']);
        }
        # weapon statlines:
        if(count($unit['weapon_stat'])) {
            $this->renderLine();
            $this->renderTable($unit['weapon_stat']);
        }

        # wizard statlines:
        if(count($unit['powers']) > 0) {
            $needs_smite = true;
            foreach($unit['powers'] as $power) {
                if($power['Psychic Power'] == 'Smite') { $needs_smite = false; }
            }
            if($needs_smite) {
                array_unshift($unit['powers'], array(
                    'Psychic Power' => 'Smite',
                    'Warp Charge'   => 5,
                    'Range'         => '18"',
                    'Details'       => 'If manifested, the closest visible enemy unit within 18" of the psyker suffers D3 mortal wounds. If the result of the Psychic test was more than 10, the target suffers D6 mortal wounds instead.'
                ));
            }
            $this->renderLine();
            $this->renderTable($unit['powers']);
        }

        # abilities:
        if(count($unit['abilities']) > 0) {
            $this->renderLine();
            $this->renderAbilities('Abilities', $unit['abilities']);
        }

        # keywords and keyword-adjacents:
        $unit['points'] = array($unit['points']);
        $lists = array(
            'Rules'    => 'rules',
            'Factions' => 'factions',
            'Keywords' => 'keywords',
            'Contents' => 'roster'
        );
        foreach($lists as $label => $data) {
            if(count($unit[$data]) > 0) {
                $allCaps = $label == 'Roster' ? false : true;
                $this->renderLine();
                $this->renderKeywords($label, $unit[$data], $allCaps);
            }
        }

        # wound tracker:
        $hasTracks = false;
        foreach($unit['model_stat'] as $type) {
            if($type['W'] > 1) { $hasTracks = true; }
        }
        if($hasTracks) {
            $this->renderLine();
            $this->currentY += 5;
            $this->renderWoundBoxes($unit);
        }

        if(count($unit['wound_track']) > 0) {
            $this->renderLine();
            $this->renderTable($unit['wound_track']);
        }

        $this->renderBorder();
        $this->renderWatermark();
    }

    protected function renderHeader($unit) {
        $draw = $this->getDraw();
        $draw->setFillColor('#000000');
        $draw->setFillOpacity(1);
        $draw->polygon(array(
            array('x' => $this->currentX + $this->margin, 'y' => 70),
            array('x' => $this->currentX + 70, 'y' => $this->margin),
            array('x' => $this->currentX + $this->maxX - 70, 'y' => $this->margin),
            array('x' => $this->currentX + $this->maxX - $this->margin, 'y' => 70),
        ));
        $draw->rectangle(($this->margin + $this->currentX), 70, 
                         ($this->maxX + $this->currentX - $this->margin), 100);
        $this->image->drawImage($draw);

        # FOC slot and PL and points:
        $draw = $this->getDraw();
        $draw->setFillColor('#FFFFFF');
        $draw->setStrokeWidth(0);
        $draw->setFontSize(20);

        $gon = new Imagick();
        $gon->readImage('../assets/octagon.png');
        $gon->resizeimage(45, 45, \Imagick::FILTER_LANCZOS, 1);
        $this->image->compositeImage($gon, Imagick::COMPOSITE_DEFAULT, $this->currentX + 58, 52);

        $gon = new Imagick();
        $gon->readImage('../assets/icon_'.$unit['slot'].'.png');
        $gon->resizeimage(35, 35, \Imagick::FILTER_LANCZOS, 1);
        $this->image->compositeImage($gon, Imagick::COMPOSITE_DEFAULT, $this->currentX + 63, 57);

        $gon = new Imagick();
        $gon->readImage('../assets/octagon.png');
        $gon->resizeimage(45, 45, \Imagick::FILTER_LANCZOS, 1);
        $this->image->compositeImage($gon, Imagick::COMPOSITE_DEFAULT, $this->currentX + 105, 52);
        $draw->setFont('../assets/title_font.otf');
        $draw->setFontSize(26);

        if(strlen($unit['power']) == 1) {
            $this->image->annotateImage($draw, 121 + $this->currentX, 84, 0, $unit['power']);
        } else {
            $this->image->annotateImage($draw, 114 + $this->currentX, 84, 0, $unit['power']);
        }

        $gon = new Imagick();
        $gon->readImage('../assets/octagon.png');
        $gon->resizeimage(45, 45, \Imagick::FILTER_LANCZOS, 1);
        $this->image->compositeImage($gon, Imagick::COMPOSITE_DEFAULT, $this->currentX + 152, 52);
        $draw->setFont('../assets/title_font.otf');
        $draw->setFontSize(20);
        if(strlen($unit['points']) == 2) {
            $this->image->annotateImage($draw, 165 + $this->currentX, 82, 0, $unit['points']);
        } else {
            $this->image->annotateImage($draw, 158 + $this->currentX, 82, 0, $unit['points']);
        }

        # unit name:
        $iters = 0;
        $title_size = 28;
        $draw->setFontSize($title_size);
        $draw->setFont('../assets/title_font.otf');
        $check = $this->image->queryFontMetrics($draw, strtoupper($unit['title']));
        while($iters < 6 && $check['textWidth'] > 430) {
            $iters += 1;
            $title_size -= 2;
            $draw->setFontSize($title_size);
            $check = $this->image->queryFontMetrics($draw, strtoupper($unit['title']));
        }
        $title_x =  ceil((($this->maxX * .5) + $this->currentX) - ($check['textWidth'] / 2));
        $this->image->annotateImage($draw, $title_x, 88, 0, strtoupper($unit['title']));
        $this->currentY += 100;
    }

    protected function renderAbilities($label='Abilities', $data=array()) {
        $this->renderText($this->currentX + 80, $this->currentY + 20, strtoupper($label), 40, 16);
        foreach($data as $label => $desc) {
            $content = trim(strtoupper($label).": $desc");
            $this->currentY += $this->renderText($this->currentX + 190, $this->currentY + 19, $content, 90);
        }
        $this->currentY += 5;
        return $this->currentY;
    }

    protected function renderWatermark() {
        $content = 'CREATED WITH BUTTSCRIBE: http://www.buttscri.be';
        $this->currentY += $this->renderText($this->currentX + 80, $this->currentY + 12, $content, 90);
    }

    protected function renderBorder() {
        $draw = $this->getDraw();
        $draw->setStrokeWidth(2);
        $draw->setStrokeColor('#222222');

        $start = array(50, 70);
        $segs = array(
            array(70, 50),
            array($this->maxX - 70, 50),
            array($this->maxX - 50, 70),
            array($this->maxX - 50, $this->currentY),
            array($this->maxX - 70, $this->currentY + 20),
            array(70, $this->currentY + 20),
            array(50, $this->currentY),
            array(50, 70)
        );
        foreach($segs as $end) {
            $draw->line($start[0] + $this->currentX, $start[1], $end[0] + $this->currentX, $end[1]);
            $start = $end;
        }
        $this->image->drawImage($draw);

        $draw = $this->getDraw();
        $draw->setFillColor('#FFFFFF');
        $draw->rectangle((50 + $this->currentX), $this->currentY + 22, $this->maxX - 50 + $this->currentX, $this->maxY - 50);
        $this->image->drawImage($draw);

        # the corners are all beefed up:
        $draw = $this->getDraw();
        $draw->setFillColor('#FFFFFF');
        $draw->setFillOpacity(1);
        $draw->polygon(array(
            array('x' => $this->currentX + 48, 'y' => $this->currentY + 22),
            array('x' => $this->currentX + 68, 'y' => $this->currentY + 22),
            array('x' => $this->currentX + 48, 'y' => $this->currentY + 2)
        ));
        $this->image->drawImage($draw);
        $draw = $this->getDraw();
        $draw->setFillColor('#FFFFFF');
        $draw->setFillOpacity(1);
        $draw->polygon(array(
            array('x' => $this->currentX + $this->maxX - 48, 'y' => $this->currentY + 22),
            array('x' => $this->currentX + $this->maxX - 68, 'y' => $this->currentY + 22),
            array('x' => $this->currentX + $this->maxX - 48, 'y' => $this->currentY + 2)
        ));
        $this->image->drawImage($draw);
    }

    protected function renderTable($rows=array(), $col_widths = array(), $width=740, $showHeaders=true) {
        # insanely dumb hack:
        if(empty($col_widths)) {
            $col_widths = array(
                'Range'       => 55,
                'Warp Charge' => 95,
                'Type'        => 75,
                'Remaining W' => 120,
                'Characteristic 1' => 110,
                'Characteristic 2' => 110,
                'Characteristic 3' => 110
            );
        }

        for($i = 0; $i < count($rows); $i++) {
            $tdraw = $this->getDraw();
            $tdraw->setFillColor('#000000');
            $draw = $this->getDraw();
            $draw->setFillOpacity(1);

            # header row:
            if($i == 0 && $showHeaders) {
                $x      = $this->currentX + 60;
                $height = $this->currentY + 22;
                $draw->setFillColor('#AAAAAA');
                $draw->rectangle($this->currentX + $this->margin + 2, $this->currentY, ($this->currentX + $width), $height);
                $this->image->drawImage($draw);
                $tdraw->setFontSize(14);
                $tdraw->setFontWeight(600);

                $first           = true;
                $new_y           = 0;
                $this->currentY = $height;
                foreach($rows[$i] as $stat => $val) {
                    $text = $stat;
                    if(strpos($stat, 'Characteristic') !== false) {
                        $text = 'Attribute';
                    } else if($stat == 'Warp Charge') {
                        $text = 'Manifest';
                    }
                    $hdraw = $this->getDrawFont();
                    $this->renderText($x, ($this->currentY - 3), strtoupper($text));
                    if($first) {
                        $x     += 220;
                        $first = false;
                    } else if(array_key_exists($stat, $col_widths)) {
                        $x += $col_widths[$stat];
                    } else {
                        $x += 45;
                    }
               }
            }

            # data row:
            $tdraw->setFontSize(12);
            $tdraw->setFontWeight(400);
            $height = 0;
            foreach($rows[$i] as $stat => $val) {
                $char_limit = ($stat == 'Details' ? 55 : 33);
                $text    = wordwrap($val, $char_limit, "\n", false);
                $lines   = substr_count($text, "\n") > 0 ? substr_count($text, "\n") : 1;
                $theight = ($lines * ($tdraw->getFontSize() + 3)) + $this->currentY + 17;
                if($theight > $height) {
                    $height = $theight;
                }
            }

            $draw = $this->getDraw();
            $draw->setFillOpacity(1);
            if($i % 2) {
                $draw->setFillColor('#EEEEEE');
            } else {
                $draw->setFillColor('#FFFFFF');
            }
            $draw->rectangle($this->currentX + $this->margin + 2, $this->currentY, 
                             ($this->currentX + $width), $height);
            $this->image->drawImage($draw);

            $x     = $this->currentX + 60;
            $new_y = 0;
            $first = true;
            foreach($rows[$i] as $stat => $val) {
                $tdraw->setFontWeight(400);
                if($first) {
                    $tdraw->setFontWeight(600);
                }
                $fake_y = $this->renderText($x, ($this->currentY + 17), $val, $char_limit);
                if($fake_y > $new_y) {
                    $new_y = $fake_y;
                }
                if($first) {
                    $x     += 220;
                    $first = false;
                } else if(array_key_exists($stat, $col_widths)) {
                    $x += $col_widths[$stat];
                } else {
                    $x += 45;
                }
            }
            $this->currentY += $new_y;
        }
    }

    protected function renderWoundBoxes($unit) {
        # TODO: roster-based boxes:
        # 7 1W guys: X X X X X X 
        # 1 2W guy: X X
        # 1 2W guy: X X

        foreach($unit['model_stat'] as $type) {
            if($type['W'] > 1) {
                $draw = $this->getDraw();
                $draw->setStrokeWidth(1);
                $draw->setFontSize(16);
                $draw->setFontWeight(600);

                $text   = wordwrap(strtoupper($type['Unit']), 22, "\n", false);
                $lines  = substr_count($text, "\n") > 0 ? substr_count($text, "\n") : 1;
                $height = (($lines + 1) * ($draw->getFontSize() + 4));
                $height = $height < 40 ? 40 : $height;

                $this->image->annotateImage($draw, 80 + $this->currentX, $this->currentY + 20, 0, $text);
                $this->image->drawImage($draw);

                $x = 340 + $this->currentX;
                for($w = 0; $w < $type['W']; $w++) {
                    if($w % 10 == 0 && $w > 0) {
                        $this->currentY += 40;
                        $x = 340 + $this->currentX;
                    }
                    $draw->setStrokeOpacity(1);
                    $draw->setStrokeColor('#000000');
                    $draw->setFillColor('#FFFFFF');
                    $draw->rectangle($x, $this->currentY, ($x + 30), ($this->currentY + 30));
                    $this->image->drawImage($draw);
                    $x += 40;
                }
                $this->currentY += $height;
            }
        }
    }

    protected function renderKeywords($label="Something", $data=array(), $allCaps = true) {
        $text = strtoupper($label);
        $this->renderText($this->currentX + 80, $this->currentY + 20, $text, 40, 16);

        $data = array_unique($data);

        $contents = implode(', ', $data);
        if($allCaps) {
            $contents = strtoupper(implode(', ', $data));
        }

        $this->currentY += $this->renderText($this->currentX + 190, $this->currentY + 19, $contents, 75);
        $this->currentY += 5;
        return $this->currentY;
    }

    public function renderToOutFile() {
        for($i = 0; $i < count($this->units); $i++) {
            $this->image->newImage($this->res * 11, $this->res * 8.5, new ImagickPixel('white'), 'pdf');
            $this->image->setResolution($this->res, $this->res);
            $this->image->setColorspace(Imagick::COLORSPACE_RGB);

            if(array_key_exists($i, $this->units)) {
                $this->renderUnit($this->units[$i], 0, 0);
            }
            $i += 1;
            if(array_key_exists($i, $this->units)) {
                $this->renderUnit($this->units[$i], ($this->res * 5.5), 0);
            }
            $this->image->writeImages($this->outFile, true);
        }
    }
}
