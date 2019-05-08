<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


class Game {

    private $level;

    private $points;

    private $current_player_row = 0;
    private $current_player_key = 0;

    private $current_enemy_row = 0;
    private $current_enemy_key = 0;

    private $player_has_moved = false;

    private $html_output;

    private $player_is_alive = true;
    private $level_number = 1;
    private $debug = [];
    private $collected_all_coins = true;

    private $acceptable_tiles = ['_', 'C'];

    public function get_debug(){
        return $this->debug;
    }


    public function __construct(?array $level_override = null, $expected_level_number)
    {
        if(isset($_COOKIE['php_game_level_number'])) {
            $this->level_number = $_COOKIE['php_game_level_number'];
            if ($_COOKIE['php_game_level_number'] != $expected_level_number ) {
                $this->level_number = $_COOKIE['php_game_level_number'];
                $level_override = null;
                $this->debug[] = 'Changing level to '. $this->level_number;
            }
        }

        if(is_null($level_override)){
            //Load the first level
            $this->level = json_decode(file_get_contents('Levels/level'.$this->level_number.'.json'));
        } else {
            $this->level = $level_override;
        }



    }

    private function door_check($action_to_take, $door = 'D' ){
        if($this->collected_all_coins == true) {
            if($action_to_take == $door) {
                //Up level count and refresh
                echo '<script>document.cookie = "php_game_level_number='. ($this->level_number + 1) .';path=/";  window.location.assign(window.location.href);</script>';
                return true;
            }
        }
        return false;
    }
    public function evaluate($move) {
        //Assume we got all coins until we find one in the array
        $this->collected_all_coins = true;
        foreach($this->level as $row_key => $row) {
            if ($key = array_search('C', $this->level[$row_key], true)) {
                $this->collected_all_coins = false;
            }
            if ($key = array_search('P', $this->level[$row_key], true)) {
                $this->current_player_key = $key;
                $this->current_player_row = $row_key;
                //We have a player
//            echo 'Found Player @'. $key;
                if ($_POST['dir'] == 'left') {
                    if (in_array($row[$key - 1], $this->acceptable_tiles )) {
                        $this->level[$row_key][$key] = '_';
                        $this->level[$row_key][$key - 1] = 'P';

                        $this->current_player_key = $key - 1;
                        $this->current_player_row = $row_key;
                        $this->player_has_moved = true;
                    }

                    $this->door_check($row[$key -1]);


                }

                if ($_POST['dir'] == 'right') {
                    //Move left
                    if (in_array($row[$key + 1], $this->acceptable_tiles)) {
                        $this->level[$row_key][$key] = '_';
                        $this->level[$row_key][$key + 1] = 'P';

                        $this->current_player_key = $key + 1;
                        $this->current_player_row = $row_key;
                        $this->player_has_moved  = true;
                    }

                    $this->door_check($row[$key +1]);
                }
                if($this->player_has_moved == false) {
                    if ($_POST['dir'] == 'up') {
                        if (in_array($this->level[$row_key - 1][$key], $this->acceptable_tiles) ) {
                            $this->level[$row_key][$key] = '_';
                            $this->level[$row_key - 1][$key] = 'P';

                            $this->current_player_key = $key;
                            $this->current_player_row = $row_key - 1;
                            $this->player_has_moved  = true;
                        }


                    }

                    if ($_POST['dir'] == 'down') {
                        if (in_array($this->level[$row_key + 1][$key], $this->acceptable_tiles)) {
                            $this->level[$row_key][$key] = '_';
                            $this->level[$row_key + 1][$key] = 'P';

                            $this->current_player_key = $key;
                            $this->current_player_row = $row_key + 1;
                            $this->player_has_moved  = true;
                        }
                    }
                }
            }
        }
        $this->debug[] = 'the door is '.($this->collected_all_coins ? 'open': 'closed');
        //Clear some stuff so we dont have conflicts
        unset($row_key);
        unset($key);
        unset($row);
        $enemy_has_moved = false;
        $try_to_move_count = 0;


        foreach($this->level as $row_key => $row) {
            if ($ekey = array_search('E', $this->level[$row_key], true)) {
                $this->debug[] = 'Found Enemy';
                $this->current_enemy_key = $ekey;
                $this->current_enemy_row = $row_key;

                while($enemy_has_moved == false && $try_to_move_count < 10) {
                    $should_move_vertical = (bool) rand(0,1);
                    $this->debug[] = 'Enemy move attempt '. $try_to_move_count;
                    if ($enemy_has_moved === false) {
                        $this->debug[] = 'Enemy is attempting to move '. ($should_move_vertical == 0 ? 'horizontally': 'vertically');
                        if (!$should_move_vertical) {

                            if ($ekey > $this->current_player_key) {
                                if ($this->level[$row_key][$ekey - 1] == '_') {
                                    $this->level[$row_key][$ekey] = '_';
                                    $this->level[$row_key][$ekey - 1] = 'E';
                                    $enemy_has_moved = true;
                                }

                                if ($this->level[$row_key][$ekey - 1] == 'P') {
                                    $this->level[$row_key][$ekey - 1] = 'O';
                                    $this->player_is_alive = false;
                                }

                            } else if ($ekey < $this->current_player_key) {
                                if ($this->level[$row_key][$ekey + 1] == '_') {
                                    $this->level[$row_key][$ekey] = '_';
                                    $this->level[$row_key][$ekey + 1] = 'E';
                                    $enemy_has_moved = true;
                                }

                                if ($this->level[$row_key][$ekey + 1] == 'P') {
                                    $this->level[$row_key][$ekey + 1] = 'O';
                                    $this->player_is_alive = false;
                                }
                            }
                        }

                        if($should_move_vertical || (!$should_move_vertical && $enemy_has_moved == false)){
                            if ($row_key > $this->current_player_row) {
                                if ($this->level[$row_key - 1][$ekey] == '_') {
                                    $this->level[$row_key][$ekey] = '_';
                                    $this->level[$row_key - 1][$ekey] = 'E';
                                    $enemy_has_moved = true;
                                }

                                if ($this->level[$row_key - 1][$ekey] == 'P') {
                                    $this->level[$row_key - 1][$ekey] = 'O';
                                    $this->player_is_alive = false;
                                }
                            } else if ($row_key < $this->current_player_row) {

                                if ($this->level[$row_key + 1][$ekey] == '_') {
                                    $this->level[$row_key][$ekey] = '_';
                                    $this->level[$row_key + 1][$ekey] = 'E';
                                    $enemy_has_moved = true;
                                }

                                if ($this->level[$row_key + 1][$ekey] == 'P') {
                                    $this->level[$row_key + 1][$ekey] = 'O';
                                    $this->player_is_alive = false;
                                }
                            }
                        }
                    }
                    $try_to_move_count++;
                }
            }
        }

    }

    private function generate_board()
    {
        $output = '<div class="jumbotron">';
        foreach ($this->level as $row) {
            foreach ($row as $part) {
                if ($part == 'X') {
                    $output .= '<button type="button" disabled class="btn btn-secondary board">&nbsp;</button>';
                } else if ($part == 'P') {
                    $output .= '<button type="button" disabled class="btn btn-primary board"><span class="fas fa-user"></span></button>';
                } else if ($part == 'E') {
                    $output .= '<button type="button" disabled class="btn btn-danger board"><span class="fas fa-dragon"></span></button>';
                } else if ($part == 'D') {
                    if ($this->collected_all_coins == true) {
                        $output .= '<button type="button" disabled class="btn btn-success board"><span class="fas fa-door-open"></span></button>';
                    } else {
                        $output .= '<button type="button" disabled class="btn btn-secondary board"><span class="fas fa-door-closed"></span></button>';
                    }
                } else if ($part == 'O') {
                    $output .= '<button type="button" disabled class="btn btn-danger board">&nbsp;</button>';
                } else if ($part == 'C') {
                    $output .= '<button type="button" disabled class="btn btn-warning board"><span class="fas fa-key"></span></button>';
                } else {
                    $output .= '<button type="button" disabled class="btn btn-light board">&nbsp;</button>';

                }
            }
            $output .= '<br>';

        }
        return $output.'</div>';
    }


    private function generate_form(){

        return '<form method="POST" >
        <button name="dir"  class="btn left-button " value="left">Left</button>
        <button name="dir" class="btn right-button " value="right">Right</button>
        <button name="dir" class="btn up-button " value="up">Up</button>
        <button name="dir" class="btn down-button" value="down">Down</button>
        <input type="hidden" name="level_number" value="'.$this->level_number.'" >
        <input type="hidden" name="level_data" value="'.urlencode(json_encode($this->level)).'">
        </form>
        <p>You are on level '.$this->level_number.'</p>
        <button value=\'document.cookie ="php_game_level_number=1; expires = Thu, 01 Jan 1970 00:00:00 GMT"\'>Clear Progress</button>
';

    }
    public function render(){

        $this->html_output = file_get_contents('templates/header.html');
        $this->html_output .= $this->generate_board();

        if($this->player_is_alive){
            $this->html_output.= $this->generate_form();
        }

        $this->html_output.= file_get_contents('templates/footer.html');



        echo $this->html_output;

    }




}

$level = null;
$level_number = 1;
if(isset($_POST) && isset($_POST['dir'])){
    $level = json_decode(urldecode($_POST['level_data']), true);
    $level_number = $_POST['level_number'];
}

$game = new Game($level, $level_number);
if(isset($_POST) && isset($_POST['dir'])) {
    $game->evaluate($_POST['dir']);
}
$game->render();
