<?php
/**/exit(); // Untuk ke-amanan file
?>
{
	"CORE": {
		"app_name": "App Todo",
		"app_logoSVG": "/image/logo/logo.svg",
		"app_logoPNG": {
			"transparent": "/image/logo/logo.png"
		},
		"app_build_version": "240714",
		"app_key_encrypt": "ABu$$9/p{^OlKJX",
		"app_private_key": {
			"app": {
				"rule": "PAIR_WITH_DATE",
				"value": "ABu$$9/p{^OlKJX"
			},
			"jwt": {
				"rule": "",
				"value": "5rByBPL$xo&QJw8"
			}
		},
		"db_host": "localhost",
		"db_user": "root",
		"db_pass": "",
		"db_name": ["app_todo"],
		"tb_prefix": "todo_",
		"session_method": "jwt",
		"session_prefix": "todo_",
		"session_user": {
			"expire_time": 360000,
			"max_user_device": 30
		}
	}
}