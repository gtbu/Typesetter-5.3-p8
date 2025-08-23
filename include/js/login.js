
$(function(){
	// if( typeof(IE_LT_10) != 'undefined' && IE_LT_10 ){
	if( window.navigator.userAgent.match(/(MSIE|Trident)/) ){
		$('#browser_warning').show();
	}
	$('#loginform .login_text').first().find('input').trigger('focus');


	window.setTimeout(function(){
		$('#login_timeout').show();
		$('#login_form').hide();
	},500000);//10 minutes would be 600,000

	//don't send plaintext password if possible
	//send instead md5 and sha1 encrypted strings
	$('#login_form').on('submit', function(){
		if (this.encrypted.checked) {

var form = document.getElementById('login_form');
var pwd = form.password.value;
var nonce = form.login_nonce.value;

var pwd_md5 = CryptoJS.MD5(pwd).toString(CryptoJS.enc.Hex);
form.elements['pass_md5'].value = CryptoJS.SHA1(nonce + pwd_md5).toString(CryptoJS.enc.Hex);
form.elements['pass_sha'].value = CryptoJS.SHA1(nonce + CryptoJS.SHA1(pwd).toString(CryptoJS.enc.Hex)).toString(CryptoJS.enc.Hex);
form.elements['pass_sha512'].value = CryptoJS.SHA512(pwd).toString(CryptoJS.enc.Hex);
form.elements['user_sha'].value = CryptoJS.SHA1(nonce + form.username.value).toString(CryptoJS.enc.Hex);

// console.log("pass_md5:", form.elements['pass_md5'].value);
// console.log("pass_sha:", form.elements['pass_sha'].value);
// console.log("pass_sha512:", form.elements['pass_sha512'].value);
// console.log("user_sha:", form.elements['user_sha'].value);
}

	});

	function sha512(pwd){

		for(var i = 0; i < 50; i++ ){
			var ints		= pwd.replace(/[a-f]/g,'');
			var salt_start	= parseInt(ints.substr(0,1));
			var salt_len	= parseInt(ints.substr(2,1));
			var salt		= pwd.substr(salt_start,salt_len);
			var shaObj		= new jsSHA(pwd+salt,'TEXT');
			pwd = shaObj.getHash('SHA-512', 'HEX');
		}

		return pwd;
	}

});
