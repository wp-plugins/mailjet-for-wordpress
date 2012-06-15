<?php

class WP_Mailjet_Utils
{
    public static function custom_notice($type, $message)
    {
        echo '<div class="'.$type.'"><p>'.$message.'</p></div>';
    }
}