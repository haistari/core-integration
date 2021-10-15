<?php

/*
 * This file is part of the HAISTAR Core Integration.
 *
 * (c) Nanda Firmansyah <nafima21@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Haistar;

class Validator
{
    static public function make(Array $params, Array $rules, Array $custom_message = [])
    {  
        $error_message = "";

        foreach ($rules as $key => $value)
        {
            if(!array_key_exists($key, $params))
            {
                if (strpos($value, 'required') !== false) 
                {
                    if(isset($custom_message))
                    {
                        if(array_key_exists($key.".required", $custom_message))
                        {
                            $error_message .= $custom_message[$key.".required"] . ", ";
                        }
                        else
                        {
                            $error_message .= $key . " must be declare, ";
                        }
                    }
                    else
                    {
                        $error_message .= $key . " must be declare, ";
                    }
                }
            }
            else
            {
                if(empty($params[$key]))
                {
                    if (strpos($value, 'required') !== false) 
                    {
                        if(isset($custom_message))
                        {
                            if(array_key_exists($key.".required", $custom_message))
                            {
                                $error_message .= $custom_message[$key.".required"] . ", ";
                            }
                            else
                            {
                                $error_message .= $key . " cannot be empty, ";
                            }
                        }
                        else
                        {
                            $error_message .= $key . " cannot be empty, ";
                        }                        
                    }
                }
            }
        }
        
        return trim($error_message, ", ");
    }
}