<?php
class User
{
    public $name;
    public $username;
    public $password;

    public function __construct($name, $username, $password)
    {
        $this->name = $name;
        $this->username = $username;
        $this->password = $password;
    }

    public function set_name($name)
    {
        return $this->name = $name;
    }

    public function get_name()
    {
        return $this->name;
    }

    public function xuatthongtin()
    {
        echo "Họ tên: " . htmlspecialchars($this->name) . "<br>";
        echo "Tài khoản: " . htmlspecialchars($this->username) . "<br>";
    }

    public function login()
    {
        if ($this->username === "admin" && $this->password === "123456") {
            return true;
        }
        return false;
    }
}
?>