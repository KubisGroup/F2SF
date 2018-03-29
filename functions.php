<?php
function dbType($type) {
	switch($type) {
		default:
			return;
		break;

		case "boolean":
			return "BOOLEAN";
		break;
		case "integer":
			return "INT";
		break;
		case "double":
			return "DOUBLE";
		break;
		case "float":
			return "FLOAT";
		break;
		case "string":
			return "TEXT";
		break;
		case "array":
			return "LONGTEXT";
		break;
		case "object":
			return "TEXT";
		break;
		case "resource":
			return "TEXT";
		break;
		case "NULL":
			return "INT";
		break;
	}
}