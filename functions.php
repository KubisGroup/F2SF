<?php
/**
 * Kubis Group F2FS API connector
 * 
 * Connect your Fler.cz eshop with superfaktura.cz. 
 * Its simple, you need only API keys not more because it is fully automated
 */

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
