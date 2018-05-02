var key="b19e5f85bb28e661ca4eacad704eb615a57cf11e";

function listen(obj,event,func) {
    if(obj.addEventListener) {
	obj.addEventListener(event,func,false);
    }
    else if(obj.attachEvent) {
	obj.attachEvent("on"+event,func);
    }
    else {
	throw "No support for events in DOM";
    }
}

function handleChange() {
    var i=0;
    // Chrome gives a value of C:\fakepath\filename.ext so this gets around
    // that issue
    if((i=document.media.content.value.lastIndexOf("\\"))>=0) {
	document.media.file.value = document.media.content.value.substr(i+1).replace(/ /,"_");
    }
    else {
	document.media.file.value = document.media.content.value.replace(/ /,"_");
    }
    startVerify();
}

function prepare() {
    listen(document.media.content,"change",handleChange);
}

var timer = null;

function startVerify() {
    if(document.media.file.value.length > 0)
	// Wait 100 ms before executing
	timer = setTimeout(checkValidity,200);
}

function checkValidity() {
    timer = null;
    var xhttp = null;
    if(window.XMLHttpRequest) {
	xhttp = new XMLHttpRequest();
    }
    else {
	xhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    var body = "service=media&nonce=&request=checkInUse&file="+escape(document.media.file.value)+"&hash="+Sha1.hash("media::checkInUse:"+key);
    xhttp.open("POST","/media/api.php",false);
    xhttp.setRequestHeader("Content-type","application/x-www-form-urlencoded");
    xhttp.send(body);
    window.response = JSON.parse(xhttp.responseText);
    var x = document.getElementById("errorcode");
    if(typeof(response)==='object' && response.response) {
	x.innerHTML = "Warning: There is a file that is already using this name. Continuing will create a new revision.";
	x.style.display = "inline";
    }
    else {
	x.innerHTML = "";
	x.style.display = "none";
    }
}

function stopVerify() {
    if(timer != null) {
	clearTimeout(timer);
	timer = null;
    }
}

