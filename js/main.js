jQuery(document).ready(function(){	
	var apaui_form = jQuery("form#apauiForm");
	var apaui_tax_input = apaui_form.find("input[name=apaui_tax]");
	var apaui_updatepost = apaui_form.find("input[name=apaui_updatepost]");
	
	apaui_form.find(".apaui_tax_class input").each(function(){
		jQuery(this).change(function(){
			apaui_tax_input.val(getdata("form#apauiForm .apaui_tax_class input"));
		});
	});

	apaui_form.find(".apaui_updatepost .input").each(function(){
		jQuery(this).change(function(){
			apaui_updatepost.val(getdata("form#apauiForm .apaui_updatepost .input"));
		});
	});

	function getdata(class_el){		
		var data =[];
		var apaui_checkboxs = jQuery(class_el);
		apaui_checkboxs.each(function(){
			if((this).type == "checkbox" || (this).type=="radio"){
				if((this).checked == true){
					data.push((this).value);
				}
			}else{
				data.push((this).name + "|" + (this).value);
			}			
		});
		data = data.join(",");
		console.log(data);
		return data;
	}
})