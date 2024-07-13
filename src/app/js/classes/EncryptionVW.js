/* eslint-disable import/no-named-default */
/* eslint-disable camelcase */
/* eslint-disable func-names */
/* eslint-disable guard-for-in */
/* eslint-disable no-restricted-syntax */
/* eslint-disable object-curly-newline */
/* eslint-disable no-lonely-if */
/* eslint-disable max-len */
/* eslint-disable lines-between-class-members */
/* eslint-disable default-case */
/* eslint-disable import/prefer-default-export */
/* eslint-disable no-param-reassign */
/* eslint-disable no-loop-func */
/* eslint-disable no-new */
/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */
/* eslint no-extend-native: ["error", { "exceptions": ["String"] }] */

import CryptoJS from 'crypto-js';

/**
 * EncryptionVW class for encrypt/decrypt that works between programming languages.
 *
 * @author Vee Winch.
 * @link https://stackoverflow.com/questions/41222162/encrypt-in-php-openssl-and-decrypt-in-javascript-cryptojs Reference.
 * @link https://github.com/brix/crypto-js/releases crypto-js.js can be download from here.
 */

export default class EncryptionVW {
	/**
     * @var integer Return encrypt method or Cipher method number. (128, 192, 256)
     */
	get encryptMethodLength() {
		const { encryptMethod } = this;
		// get only number from string.
		// @link https://stackoverflow.com/a/10003709/128761 Reference.
		const aesNumber = encryptMethod.match(/\d+/)[0];
		return parseInt(aesNumber, 10);
	}// encryptMethodLength

	/**
     * @var integer Return cipher method divide by 8. example: AES number 256 will be 256/8 = 32.
     */
	get encryptKeySize() {
		const aesNumber = this.encryptMethodLength;
		return parseInt(aesNumber / 8, 10);
	}// encryptKeySize

	/**
     * @link http://php.net/manual/en/function.openssl-get-cipher-methods.php Refer to available methods in PHP if we are working between JS & PHP encryption.
     * @var string Cipher method.
     *              Recommended AES-128-CBC, AES-192-CBC, AES-256-CBC
     *              due to there is no `openssl_cipher_iv_length()` function in JavaScript
     *              and all of these methods are known as 16 in iv_length.
     */
	get encryptMethod() {
		return 'AES-256-CBC';
	}// encryptMethod

	/**
     * Decrypt string.
     *
     * @link https://stackoverflow.com/questions/41222162/encrypt-in-php-openssl-and-decrypt-in-javascript-cryptojs Reference.
     * @link https://stackoverflow.com/questions/25492179/decode-a-base64-string-using-cryptojs Crypto JS base64 encode/decode reference.
     * @param string encryptedString The encrypted string to be decrypt.
     * @param string key The key.
     * @return string Return decrypted string.
     */
	decrypt(encryptedString, key) {
		const json = JSON.parse(CryptoJS.enc.Utf8.stringify(CryptoJS.enc.Base64.parse(encryptedString)));

		const salt = CryptoJS.enc.Hex.parse(json.salt);
		const iv = CryptoJS.enc.Hex.parse(json.iv);

		const encrypted = json.ciphertext;// no need to base64 decode.

		let iterations = parseInt(json.iterations, 10);
		if (iterations <= 0) {
			iterations = 999;
		}
		const encryptMethodLength = (this.encryptMethodLength / 4);// example: AES number is 256 / 4 = 64
		const hashKey = CryptoJS.PBKDF2(key, salt, { hasher: CryptoJS.algo.SHA512, keySize: (encryptMethodLength / 8), iterations });

		const decrypted = CryptoJS.AES.decrypt(encrypted, hashKey, { mode: CryptoJS.mode.CBC, iv });

		return decrypted.toString(CryptoJS.enc.Utf8);
	}// decrypt

	/**
     * Encrypt string.
     *
     * @link https://stackoverflow.com/questions/41222162/encrypt-in-php-openssl-and-decrypt-in-javascript-cryptojs Reference.
     * @link https://stackoverflow.com/questions/25492179/decode-a-base64-string-using-cryptojs Crypto JS base64 encode/decode reference.
     * @param string string The original string to be encrypt.
     * @param string key The key.
     * @return string Return encrypted string.
     */
	encrypt(string, key) {
		const iv = CryptoJS.lib.WordArray.random(16);// the reason to be 16, please read on `encryptMethod` property.

		const salt = CryptoJS.lib.WordArray.random(256);
		const iterations = 999;
		const encryptMethodLength = (this.encryptMethodLength / 4);// example: AES number is 256 / 4 = 64
		const hashKey = CryptoJS.PBKDF2(key, salt, { hasher: CryptoJS.algo.SHA512, keySize: (encryptMethodLength / 8), iterations });

		const encrypted = CryptoJS.AES.encrypt(string, hashKey, { mode: CryptoJS.mode.CBC, iv });
		const encryptedString = CryptoJS.enc.Base64.stringify(encrypted.ciphertext);

		const output = {
			ciphertext: encryptedString,
			iv: CryptoJS.enc.Hex.stringify(iv),
			salt: CryptoJS.enc.Hex.stringify(salt),
			iterations,
		};

		return CryptoJS.enc.Base64.stringify(CryptoJS.enc.Utf8.parse(JSON.stringify(output)));
	}// encrypt
}
