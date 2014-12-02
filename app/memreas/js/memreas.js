/////////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
/////////////////////////////////


/////////////////////
// memreas action ws
/////////////////////
var sid=0;
jQuery.fetchWS = function (base_url) {

	//var base_url = '/index';
	var obj = new Object();
	obj.xml = $("#action_input").val();
    var json_actionInputValue = JSON.stringify(obj, null, '\t');
    var data = "";
    var result = "";
    
    //if () {}
    data = '{"action": "' + $("#input_action").val() + '", ' + 
    		'"type":"jsonp", ' + 
    		'"codebase":"' + $("#codebase").val() + '", ' + 
    		'"json": ' + json_actionInputValue  + 
    		'}';
    
 	$.ajax( {
	  type:'post', 
	  url: base_url ,
	  dataType: 'jsonp',
	  data: { sid: $("#sid").val(), json: data},
	  success: function(json){
	  	//var resp = JSON.stringify(json, null, '\t');
	  	//var resp = jQuery.parseXML(json);
		var resp = json;
	  	alert("hi");
	  	var html_str = "";
	  	var html_button = "";
	  	$("#action_output").val(resp.data);
	  	if($("#input_action").val() == 'login'){
	  		var obj = jQuery.parseJSON( resp );
	  		xmlDoc = $.parseXML( obj.data ),
	  		$xml = $( xmlDoc ),
	  		$sid = $xml.find( "sid" );
	  		$("#sid").val($sid.html()) ;
	  	}
	  	return true;
	  },
	  error: function (jqXHR, textStatus, errorThrown) {
       	alert(jqXHR.responseText);
       	alert(jqXHR.status);
    	alert(textStatus);
       	alert(errorThrown);
       	$("#action_output").val(jqXHR.responseText);
	  }
	});
	return false;
}

