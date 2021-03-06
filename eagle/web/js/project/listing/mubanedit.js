//CKEDITOR.replace('itemdescription');

var imgUploadUrl = global.baseUrl + 'item/muban/uploadimg';
var imgDeleteUrl = global.baseUrl + 'item/muban/deleteimg';

$('.excludeship').click(function(){
	if($(this).prop('checked'))
		$(this).parent().children('div').find('input[type=checkbox]').prop('checked',true);
	else
		$(this).parent().children('div').find('input[type=checkbox]').removeAttr('checked');
});
$(document).ready(function() {
	$('#a').bootstrapValidator({
		feedbackIcons: {
            valid: 'glyphicon glyphicon-ok',
            invalid: 'glyphicon glyphicon-remove',
            validating: 'glyphicon glyphicon-refresh'
        },
		fields: {
			selleruserid: {
                validators: {
                    notEmpty: {
                        message: 'The selleruserid is required and cannot be empty'
                    }
                }
            },
			primarycategory: {
                validators: {
                    notEmpty: {
                        message: 'The primarycategory is required and cannot be empty'
                    }
                }
            },
            itemtitle: {
                validators: {
                    notEmpty: {
                        message: 'The itemtitle is required and cannot be empty'
                    },
                    stringLength: {
                        min: 1,
                        max: 80,
                        message: 'The itemtitle must be more than 1 and less than 80 characters long'
                    },
                }
            },
            itemdescription: {
                validators: {
                    notEmpty: {
                        message: 'The itemdescription is required and cannot be empty'
                    }
                }
            },
            'shippingdetails[ShippingServiceOptions][0][ShippingService]': {
                validators: {
                    notEmpty: {
                        message: 'The shippingservice.1 is required and cannot be empty'
                    }
                }
            },
            'paymentmethods[]': {
                validators: {
                    notEmpty: {
                        message: 'The paymentmethods is required and cannot be empty'
                    }
                }
            },
            paypal: {
                validators: {
                	notEmpty: {
                        message: 'The paypal is required and cannot be empty'
                    },
                	emailAddress: {
                        message: 'The paypal is not a valid email address'
                    },
                }
            },
		}
	});
//	$('.donext').click(function() {
//        $('#a').bootstrapValidator('validate');
//    });
});
/*
*	2016/4/21
*	yht
*	?????????????????????????????????
 */
$("[name='template']").on("change",function(){
	var template = $(this).val();
	$.post(global.baseUrl+'listing/ebaymuban/listingbasic',{id:template},function(data){
		if(data=='block'){
			$(".listing_basic").show();
			$(".listing_basic").children(2).css('width','900px');
		}else{
			$(".listing_basic").hide();
		}
	})
});
$('#siteid').bind("change",function(){
	$('.category').val('');
	document.a.submit()});
$('#listingtype').bind("change",function(){
	document.a.action="";
	document.a.submit()});
$('#selleruserid').bind("change",function(){
	document.a.action="";
	document.a.submit()});
$('#primarycategory').bind("change",function(){
	if(isNaN($('#primarycategory').val())){
		bootbox.alert('?????????????????????ID');
		$('#primarycategory').val('');
	}else{
	document.a.action="";
	document.a.submit();
	}});
$('#secondarycategory').bind("change",function(){
	if(isNaN($('#secondarycategory').val())){
		bootbox.alert('?????????????????????ID');
		$('#secondarycategory').val('');
	}});
//????????????????????????
//1.??????????????????????????????????????????
$('.profile_save').click(function(){
	var obj=$(this);
	//????????????????????????????????????
	// var p = $(this).parent().parent().parent().parent().parent();
	// var type = p.attr('id'); 
	// //???????????????????????????
	// // $('#'+type+' .save_name_div').show();
	// console.log($('#'+type+' .save_name_div').parent().parent());
	// console.log("'#'+type+' .save_name_div'");
	$(obj).parent().hide();
    $(obj).parent().next().show();


});

$('.profile_cancle').click(function(){
	var obj=$(this);
    $(obj).parent().hide();
    $(obj).parent().prev().show();
});

//2.??????????????????????????????????????????????????????
$('.profile_save_btn').click(function(){
	$.showLoading();
	var obj=$(this);
	//????????????????????????????????????
	var p = $(obj).parents('div[class="subbox"]');
	var type = p.attr('id'); 
	var save_name = $('#'+type+' .save_name').val();
	//??????????????????form?????????????????????
	// var p2 = $(this).parent().parent().parent();
	var p2 = $(obj).parents('div[class="subbox"]');
	var val = p2.find('input[type=text],input:checked,select,textarea').serialize();
	val=val.replace(/%5B%5D/g,'----').replace(/%5B/g,'\\\\').replace(/%5D/g,'//').replace(/----/g,'[]');
	//ajax??????
	val += '&save_name='+save_name+'&type='+type;
	$.post(global.baseUrl+'listing/ebaymuban/profilesave',val,function(r){
		$.hideLoading(); 
		var _r = eval('('+r+')');
        if (_r.ack == 'success'){
            $('#'+type+'_profile').append('<option value="'+_r.id+'">'+save_name+'</option>');
            bootbox.alert('???????????????');
            // $('#'+type+' .save_name_div').hide();
            $(obj).parent().hide();
    		$(obj).parent().prev().show();
        }else if(_r.ack == 'failure'){
        	bootbox.alert(_r.msg);
        }
        
    });
	
});
//??????????????????
$('.profile_del').click(function(){
	var p = $(this).parents('div[class="subbox"]');
	var type = p.attr('id'); 
    var select_id=type+"_profile";
    var profile=$('#'+type+'_profile').val();
    if(profile.length == 0){
        bootbox.alert('?????????????????????????????????');
        return false;
    };
    bootbox.confirm("?????????????????????????????????",function (res){
    	if(res == true){
	    	$.showLoading();
	        $.post(global.baseUrl+'listing/ebaymuban/profiledel',{'id':profile},function(r){
	            var _r = eval('('+r+')');
	            $.hideLoading(); 
	            if (_r.ack == 'success'){
	                $('#'+select_id+' option[value='+profile+']').remove();
	                bootbox.alert('???????????????');
	            }else if(_r.ack == 'failure'){
	                bootbox.alert(_r.msg);
	            }
	            return false;
	        });
    	}
    });
});
//??????????????????
$('.profile_load').click(function(){
	// var p = $(this).parent().parent().parent().parent();
	var p = $(this).parents('div[class="subbox"]');
    var type=p.attr('id');
    var profile=$('#'+type+'_profile').val();
    if(profile.length == 0){
    	bootbox.alert('????????????????????????????????????');
        return;
    }
    $.showLoading();
    $.get(global.baseUrl+'listing/ebaymuban/profileload',{'id':profile} ,function(r){
        var d=$.parseJSON(r);
        p.find(':checkbox').removeAttr('checked').trigger('change');
        for (k in d)
		{
            var name=k.replace(/\\\\/g,'\\[').replace(/\/\//g,'\\]');
            if ($.isArray(d[k]))
			{
            	if(name.indexOf("ShipToLocation") > 0){
                	$('input[name='+name+'\\[\\]]').removeAttr('disabled');
                	if($.inArray('Worldwide',d[k])){
                		$('input[name='+name+'\\[\\]]').removeAttr('checked');
                   	}
				}
            	if(name.indexOf("ExcludeShipToLocation") > 0){
            		$('#changeexclude').text(d[k]);
            	}
                for (j in d[k])
				{
                    if(d[k][j].length >0&&typeof(d[k][j]) != 'function')
					{
                        $('input[name='+name+'\\[\\]][value="'+d[k][j].replace('/','\\/')+'"]').prop('checked','checked').trigger('change');
                    }
                }
            }
			else
			{
				if($('input[name='+name+']').is('input[type=checkbox]'))
				{
                    $('input[name='+name+']').prop('checked','checked').trigger('change');
                }
				
				if($('input[name='+name+']').is('input[type=radio]'))
				{
					$('input[name='+name+']').removeAttr('checked');
					$('input[name='+name+'][value="'+d[k].replace('/','\\/')+'"]').prop('checked','checked').trigger('change');
//					if(d[k] == 'false')
//					{
//						$("#buyer_n").prop('checked', 'checked');
//					}
//					else if(d[k] == 'true')
//					{
//						$("#buyer_y").prop('checked', 'checked');
//					}
                }
				else
				{
					$('input[name='+name+']').val(d[k]).trigger('change');	
				}
                $('select[name='+name+']').val(d[k]).trigger('change');
                $('textarea[name='+name+']').val(d[k]).trigger('change');
            }
            if(name=='shippingset'){
            	$('div[id^=shippingservice]').show();
                $('div[id^=inshippingservice]').show();
            }
        }
        $('#'+type+'_profile').val(profile);
        $.hideLoading();
        bootbox.alert('????????????');
    });
});

//??????????????????????????????css??????
function showscrollcss(str){
	var _eqtmp = new Array;
	_eqtmp['account'] = 0;
	_eqtmp['siteandspe'] = 1;
	_eqtmp['titleandprice'] = 2;
	_eqtmp['picanddesc'] = 3;
	_eqtmp['shippingset'] = 4;
	_eqtmp['returnpolicy'] = 5;
	_eqtmp['buyerrequire'] = 6;
	_eqtmp['plusmodule'] = 7;
	//??????????????????
	$('.left_pannel p a').css('color','#333');
	$('.left_pannel p a').eq(_eqtmp[str]).css('color','rgb(165,202,246)');
	return false;
}
function doaction(str){
	$('#a').bootstrapValidator('validate');
	if($('#a').data('bootstrapValidator').isValid()==false){

		if($('small[class="help-block"][data-bv-result="INVALID"]').length > 0){
			var strg='<div style="color:red">';
			// for (var i = 0; i< $('small[class="help-block"]').length; i++) {
			// 	strg += $('small[class="help-block"]:eq('+i+')').html()+'<br>';
			// }
			strg += $('small[class="help-block"][data-bv-result="INVALID"]:eq(0)').html()
			strg += '</div>';
			bootbox.alert(strg);
		}
		return false;
	}
	$('#act').val(str);
//	if($("#a").form('validate')==true){
		if(str=='verify'){
			$('#a').attr('target','_blank');
		}else{//save
			//??????variation sku ??????????????????
			if($('input[name="isMuti"]:checked').val() == 1){
				if($('#variation_table tr th').length < 6){
					$.alert('??????????????????');
					return false;
				}
				check=true;
				$('#variation_table input').each(function(){
					if($(this).attr('name') !='sku[]' && $(this).attr('name') !='img[]' && $(this).val().replace(/ /g,'').length <1){
						check=false;
					}
				});
				if (check == false){
					$.alert('?????????????????????');
					return false;
				}
				//????????????
				var skuArray = [],checkSku = true;
				$('input[name^=v_sku]').each(function(){
					var val = $.trim($(this).val());
					if ($.inArray(val,skuArray) == -1){
						skuArray.push(val);
					}else{
						checkSku = false;
					}
				});
				
				if(checkSku == false){
					$.alert('SKU???????????????');
					return false;
				}
				
				//?????????????????????
				var attrNameArray = [],checkAttrName = true;
				$('input[name^=nvl_name]').each(function(){
					var attrVal = $.trim($(this).val());
					if ($.inArray(attrVal,attrNameArray) == -1){
						attrNameArray.push(attrVal);
					}else{
						checkAttrName = false;
					}
				});
				
				if(checkAttrName == false){
					$.alert('????????????????????????');
					return false;
				}
				
				//?????????????????????????????????
				$('#variation_table input').each(function(){
					$(this).attr('value', $.trim($(this).val()));
				});
			}
			
			$('#a').attr('target','_self');
//			$('#a').attr('target','_blank');
		}
		document.a.submit();
		document.a.target="";
	    document.a.action="";
		$("#act").attr("value","");
//	}
}

//??????????????????
function checkitem(){
	$('#a').bootstrapValidator('validate');
	if($('#a').data('bootstrapValidator').isValid()==false){
		if($('small[class="help-block"][data-bv-result="INVALID"]').length > 0){
			var strg='<div style="color:red">';
			// for (var i = 0; i< $('small[class="help-block"]').length; i++) {
			// 	strg += $('small[class="help-block"]:eq('+i+')').html()+'<br>';
			// }
			strg += $('small[class="help-block"][data-bv-result="INVALID"]:eq(0)').html()
			strg += '</div>';
			bootbox.alert(strg);
		}
		// alert('?????????????????????');
		return false;
	}
	$.showLoading();
	if($('#itemtitle').val().length==0){$.hideLoading();bootbox.alert('?????????????????????');return false;}
	if($('#selleruserid').val().length==0){$.hideLoading();bootbox.alert('?????????????????????');return false;}
	$.post(global.baseUrl+'listing/ebaymuban/checkrepeatmuban',{itemtitle:$('#itemtitle').val(),sku:$('#sku').val(),selleruserid:$('#selleruserid').val()},function(data){
		$.hideLoading();
		if(data!=''){
			bootbox.alert('??????????????????'+data+'???????????????????????????,?????????????????????');
		}else{
			bootbox.alert('?????????????????????????????????');
		}
	});
}
//???????????????????????????
function preview(){
	$('#a').bootstrapValidator('validate');
	if($('#a').data('bootstrapValidator').isValid()==false){
		if($('small[class="help-block"][data-bv-result="INVALID"]').length > 0){
			var strg='<div style="color:red">';
			// for (var i = 0; i< $('small[class="help-block"]').length; i++) {
			// 	strg += $('small[class="help-block"]:eq('+i+')').html()+'<br>';
			// }
			strg += $('small[class="help-block"][data-bv-result="INVALID"]:eq(0)').html()
			strg += '</div>';
			bootbox.alert(strg);
		}
		return false;
	}
	//if (typeof(KE.g['itemdescription'].iframeDoc) == 'object'){
    //    $('#itemdescription').val(KE.util.getData('itemdescription'));
    //}
    //$('#itemdescription').val(KE.util.getData('itemdescription'));
    document.a.target="_blank";
    document.a.action=global.baseUrl+"listing/ebaymuban/preview";
    document.a.submit();
    document.a.target="";
    document.a.action="";
    $("#act").attr("value","");

}
//??????Ebay????????????
//function loadStoreCategory(selleruserid){
//	if(typeof(selleruserid)!='undefined'){
//	$.ajax({
//		type: 'get',
//		url:global.baseUrl+"listing/ebaystorecategory/data?selleruserid="+selleruserid,
//		//data: {keys: selleruserid},
//		cache: false,
//		dataType:'json',
//		beforeSend: function(XMLHttpRequest){
//		},
//		success: function(data){
//			$('#storecategoryid').combotree('loadData', convertTree(data));
//			$('#storecategory2id').combotree('loadData', convertTree(data));
//		},
//		error: function(XMLResponse){
//			bootbox.alert('eBay???????????????????????????');
//		}
//	});	}
//}
//??????Ebay?????????????????? ??????json??????
function convertTree(rows){
    nodes = [];  
   // ??????????????????
   for(var i = 0; i< rows.length; i++){  
       var row = rows[i];  
       if (row.category_parentid==0){  
           nodes.push({  
               id:row.categoryid,  
               text:row.category_name
           });  
       }  
   }  
     
   var toDo = [];  
   for(var i = 0; i < nodes.length; i++){  
       toDo.push(nodes[i]);  
   }  
   while(toDo.length){  
       var node = toDo.shift();    // ????????? 
       // ??????????????? 
       for(var i=0; i<rows.length; i++){  
           var row = rows[i];  
           if (row.category_parentid == node.id){  
        	   var child = {id:row.categoryid,text:row.category_name};  
               if (node.children){  
                   node.children.push(child);  
               } else {  
                   node.children = [child];  
               }  
               toDo.push(child);  
           }  
       }  
   }
   return nodes;
}
//??????Ebay???????????????????????????????????????
//$('#selleruserid').change(function(){
//	loadStoreCategory($(this).val());
//})
//???????????????????????????????????????
$(function(){
	$('#a').attr('target','_self');
	$("#act").attr("value","");
//	var selleruserid = $('#selleruserid').val();
//	loadStoreCategory(selleruserid);
});

//????????????
function inputbox_left(inputId,limitLength,text){
    var o=document.getElementById(inputId);
    if(text==undefined){
        left=limitLength-o.value.length;
    }else{
        left=limitLength-text.length;
    }
    $('#length_'+inputId).html(left);
    if(left>=0){
        $('#length_'+inputId).css({'color':'green'});
    }else{
        $('#length_'+inputId).css({'color':'red'});
    }
}

function showihc(){
	if($('#shippingdetails_shippingdomtype').val()=='Calculated'||$('#shippingdetails_shippinginttype').val()=='Calculated'){
		$('#ihc').show();
	}else{
		$('#ihc').hide();
	}
}
function doshow(){
//	id=$('#tmp').val();
//	newid=parseInt(id)+1;
//	if(id<=3){
//		$('#shippingservice_'+id).show();
//		$('#tmp').val(newid);
//	}
	var id = 4-($('.shipping:hidden').length);
	$('#shippingservice_'+id).show();
}
function dohide(){
	id=$('#tmp').val();
	newid=parseInt(id)-1;
	if(id>1){
		$('#shippingservice_'+(id-1)).hide();
		$('#tmp').val(newid);
	}
}
function do2show(){
//	id=$('#intmp').val();
//	newid=parseInt(id)+1;
//	if(id<=4){
//		$('#inshippingservice_'+id).show();
//		$('#intmp').val(newid);
//	}
	var id = 5-($('.inshipping:hidden').length);
	$('#inshippingservice_'+id).show();
}
function do2hide(){
	id=$('#intmp').val();
	newid=parseInt(id)-1;
	if(id>0){
		$('#inshippingservice_'+(id-1)).hide();
		$('#intmp').val(newid);
	}
}
function hideshipping(obj){
	// $(obj).parent().hide();
    $(obj).parent().remove();
}
//?????????????????????
function delImgUrl_input(imgdiv) {
	
	// ???????????????????????????????????????????????? ????????? ????????????
//	var status = $(imgdiv).parent().children('input[type="text"]').attr(
//			'status');
//	var urlPath = $(imgdiv).parent().children('img').attr('src');
//	if (status == 1) {
//		if(confirm('????????????????')){
//			$.ajax({
//				url : imgDeleteUrl,
//				type : 'post',
//				data : {
//					urlpath : urlPath
//				},
//				success : function(data) {
//					if (data) {
//						$(imgdiv).parent().empty();
//					} else {
//						showmsg('??????', '????????????,?????????', 1000);
//					}
//				}
//			})
//		}
//	} else {
		$(imgdiv).parent().remove();
//		$(imgdiv).parent().children('img').attr('src','');
//		$(imgdiv).parent().children('input[type="text"]').val('');
//	}
}
//??????????????????????????????
function Addimgurl_input(src) {
	if (typeof (src) == 'undefined') {
		src = '';
	}
	$('#divimgurl')
			.append(
					"<div><img src='"
							+ src
							+ "' width='50' height='50'> <input type='text' class='iv-input' id='imgurl"
							+ (Math.random() * 10000).toString()
									.substring(0, 4)
							+ "' name='imgurl[]' size='60'  onblur='javascript:imgurl_input_blur(this)' value="
							+ src
							+ "> <input class='iv-btn btn-search' type='button' value='??????' onclick='delImgUrl_input(this)'> <input class='iv-btn btn-search' type='button' value='????????????' onclick='javascript:localimgup(this)' ><input class='iv-btn btn-search' type='button' value='?????????ebay' onclick='UploadSiteHostedPictures(this)' ></div>");
}
//??????????????????
function imgurl_input_blur(obj) {
	var t = $(obj).val();
	$(obj).parent().children('img').attr('src', t);
}

//??????????????????
function localimgup(obj){
	var tmp='';
	$('#img_tmp').unbind('change').on('change',function(){
		$.showLoading();
		$.uploadOne({
		     fileElementId:'img_tmp', // input ?????? id
			 //???????????????????????????????????????success???????????? 
			 //data: ????????????????????????????????????amazon???????????????{original:... , thumbnail:.. } 
			 onUploadSuccess: function (data){
				 $.hideLoading();
		    	 tmp = data.original;
		    	 $(obj).parent().children('input[type="text"]').val(tmp);
		    	 $(obj).parent().children('img').attr('src',tmp);
		     },
		     		     
		     // ??????????????????????????????????????????error???????????????  
		     onError: function(xhr, status, e){
		    	 $.hideLoading();
				 alert(e);
		     }
		});
	});
	$('#img_tmp').click();
}
//$('#btn-uploader').click(function(){
//	$.uploadOne({
//	     fileElementId:'img_tmp', // input ?????? id
//		 //???????????????????????????????????????success???????????? 
//		 //data: ????????????????????????????????????amazon???????????????{original:... , thumbnail:.. } 
//		 onUploadSuccess: function (data){
//	    	 bootbox.alert(data);
//	     },
//	     		     
//	     // ??????????????????????????????????????????error???????????????  
//	     onError: function(xhr, status, e){
//	    	 $.hideLoading();
//			 bootbox.alert(xhr);
//	     }
//	});
//});
//
$('#btn-uploader').batchImagesUploader({
	localImageUploadOn : true,   
    fromOtherImageLibOn: false , 
	imagesMaxNum : 5,
	fileMaxSize : 800 , 		
	fileFilter : ["jpg","jpeg","gif","pjpeg","png"],
	maxHeight : 100, 		maxWidth : 100,
//	initImages : existingImages, 
	fileName: 'product_photo_file',
    onUploadFinish : function(imagesData , errorInfo){
    	if(errorInfo){
    		bootbox.alert(errorInfo);
    	}else{
	    	for(var i in imagesData){
	    		var url = imagesData[i].original;
	    		Addimgurl_input(url);
	    	}
    	}
    	$('.mutiuploader').hide();
    },
    onDelete : function(data){
	}
	
});
//???????????????ebay
function UploadSiteHostedPictures(obj) {
	bootbox.alert('??????????????????eBay???????????????????????????eBay??????,??????????????????????????????');
	var status = $(obj).parent().children('input[type="text"]');
	var urlPath = $(obj).parent().children('img').attr('src');
	var selleruserid = $('#selleruserid').val();
	if (status.val()=="") {
		bootbox.alert('??????????????????????????????');
		return;
	}
	$.showLoading();
	$.post(global.baseUrl + 'listing/ebaymuban/uploadimg',{img:urlPath,selleruserid:selleruserid},function(data){
		$.hideLoading();
		if(data.status){
			status.val(data.data);
			$(obj).parent().children('img').attr('src',data.data);
			bootbox.alert("????????????");
		}else{
			bootbox.alert(data.data);
		}
	},"json");
}

//??????????????????
function doset(ca){
	var Url=global.baseUrl +'listing/ebaystorecategory/mubansetstorecategory';
	$.ajax({
        type : 'post',
        cache : 'false',
        data : {selleruserid : $('#selleruserid').val(),cid:ca},
		url: Url,
        success:function(response) {
        	$('#categorysetModal .modal-content').html(response);
        	$('#categorysetModal').modal('show');
        }
    });
}

//??????ebay????????????
//type:primary:????????????second????????????
function searchcategory(typ){
	var Url=global.baseUrl +'listing/ebaymuban/searchcategory';
	$.ajax({
        type : 'get',
        cache : 'false',
        data : {siteid:$('#siteid').val(),typ:typ},
		url: Url,
        success:function(response) {
        	$('#searchcategoryModal .modal-content').html(response);
        	$('#searchcategoryModal').modal('show');
        }
    });
}
//???????????????????????????
function goto(str){
	$('html,body').animate({scrollTop:$('#'+str).offset().top}, 800);
}

//???????????????????????????
function mutiupload(){
	$('#btn-uploader').click();
	$('.mutiuploader').show();
}

//?????????????????????
function mutiattribute(primarycategory,siteid){
	if($('#th3 option').length == 0){
		$.showLoading();
		$.ajax({
	        type: 'GET',
	        url: "/listing/ebaymuban/variation?primarycategory="+ primarycategory + "&siteid=" + siteid,
//	        data: {
//	        	primarycategory:primarycategory,
//	        	siteid:siteid
//	        },
	        dataType: 'json',
	        success: function (data) {
	            $.hideLoading();
	            var option_str = '<select name="v_productid_name" class="eagle-form-control" style="margin:0px">';
	            if(data.data.upcenabled != undefined&&data.data.upcenabled == 'Required'){
	            	option_str += '<option value="UPC" selected="true">UPC</option>';
	            }else if(data.data.isbnenabled != undefined&&data.data.isbnenabled == 'Required'){
	            	option_str += '<option value="EAN" selected="true">EAN</option>';
	            }else if(data.data.eanenabled != undefined&&data.data.eanenabled == 'Required'){
	            	option_str += '<option value="ISBN" selected="true">ISBN</option>';
	            }else{
	            	option_str += '<option value="UPC">UPC</option><option value="EAN">EAN</option><option value="ISBN">ISBN</option>';
	            }
	            option_str += '</select>';
	            $('#th3').html('');
	            $('#th3').append(option_str);
	        },
	        error:function(){
	            $.hideLoading();
	            bootbox.alert("????????????, ??????????????????");
	        },
	    });
	}
	if($('#mutiattribute').css('display') == "none"){
		$('#mutiattribute').css('display','block');
	}else{
		$('#mutiattribute').css('display','none');
	}
}

function replaceFloat(e) {
    var value = $(e).val().replace(/[^0-9]/g, '');
    var val = value.replace(/\b(0+)/gi,"");
    $(e).val(val);
}

function replacePrice(e) {
    var value = $(e).val().replace(/[^0-9.]/g, '');
    $(e).val(value);
}

