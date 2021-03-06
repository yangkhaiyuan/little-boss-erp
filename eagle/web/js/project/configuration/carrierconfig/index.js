$(function(){	
	$('#carrier_code').val($('input[name=carrier_code]').val());
	$(".search").change(function(){
	 	var Url=global.baseUrl +'configuration/carrierconfig/index?carrier_code='+$(this).val();
	 	window.location.href=Url; 

	});

	$(".logisticsName").click(function(){		
		if($(this).children().attr('style')==""){
			$(this).children().eq(0).attr('style','display:none;');
			$(this).children().eq(1).attr('style','');
			carrier_code = $(this).attr("name");
			$('#search_carrier_code').val(carrier_code);
		}
		else{
			$(this).children().eq(0).attr('style','');
			$(this).children().eq(1).attr('style','display:none;');
			$('#search_carrier_code').val('');
		}
				
		$.ajax({
	        type : 'post',
	        cache : 'false',
	        data : {
	        	carrier_code:carrier_code,
	        },
			url: '/configuration/carrierconfig/get-sys-carrier-info-new',
	        success:function(response) {
	        	$('#syscarrier_show_div_'+carrier_code).html(response);
	        }
	    });
		
		$(this).parent().next().toggleClass('myj-hide');
	});
	
	$(".overseaName").click(function(){		
		if($(this).children().attr('style')==""){
			$(this).children().eq(0).attr('style','display:none;');
			$(this).children().eq(1).attr('style','');
			warehouse_id = $(this).attr("data");
			warehouse_show = $(this).attr("name");
			$('#search_carrier_code').val(warehouse_show);
		}
		else{
			$(this).children().eq(0).attr('style','');
			$(this).children().eq(1).attr('style','display:none;');
			$('#search_carrier_code').val('');
		}

		$.ajax({
	        type : 'post',
	        cache : 'false',
	        data : {
	        	warehouse_id:warehouse_id,
	        	type:'syscarrier',
	        	code:warehouse_show,
	        },
			url: '/configuration/warehouseconfig/get-oversea-warehouse-info',
	        success:function(response) {
	        	$('#show_div_'+warehouse_show).html(response);
	        }
	    });
		
		$(this).parent().next().toggleClass('myj-hide');
	});
	
	$(".customName2").click(function(){		
		if($(this).children().attr('style')==""){
			$(this).children().eq(0).attr('style','display:none;');
			$(this).children().eq(1).attr('style','');
			carrier_code = $(this).attr("name");
			$('#search_carrier_code').val(carrier_code);
		}
		else{
			$(this).children().eq(0).attr('style','');
			$(this).children().eq(1).attr('style','display:none;');
			$('#search_carrier_code').val('');
		}
	
		carrier_code = $(this).attr("name");
		carrier_type = $(this).attr("data");
		
		$.ajax({
	        type : 'post',
	        cache : 'false',
	        data : {
	        	carrier_code:carrier_code,
	        	carrier_type:carrier_type
	        },
			url: '/configuration/carrierconfig/get-self-carrier-info',
	        success:function(response) {
	        	$('#excelcarrier_show_div_'+carrier_code).html(response);
	        }
	    });
		
		$(this).parent().next().toggleClass('myj-hide');
	});
	
	$(".tablist_class").click(function(){
		$('#search_tab_active').val($(this).attr("data"));
	});
	
	if(typeof ShippingJS != 'undefined')
		ShippingJS.init();

	//loadCarrierList();
	
	$search_code=$('#search_carrier_code').val();
	if($search_code!=''){
		$("div[name='"+$search_code+"']").trigger('click');
		if($('#search_tab_active').val()=='apicarrier')
			location.hash="syscarrier_show_div_"+$search_code;
		else if($('#search_tab_active').val()=='customtracking')
			location.hash="excelcarrier_show_div_"+$search_code;
		else
			location.hash="show_div_"+$search_code;
	}

});

//???????????????????????????
function openOrCloseAccount(id,is_used,code,account){
	//??????wish???????????????wish???????????????
	if(is_used == 1 && code == "lb_wishyou")
	{
		//??????v2??????????????????
		var Url = global.baseUrl +'platform/wish-postal-v2/get-url';
		$.ajax({
			type : 'post',
			dataType: 'json',
			data : { wish_account_id:id },
			url: Url,
			success:function(response) 
			{
				if(response['status'] == 0)
				{
					$.ajax({
						type:'get',
						url:response['url'],
			          	dataType: 'json',
			           	xhrFields: {
			           		withCredentials: true
			           	},
			           	success: function(result) 
			           	{
			           		//alert(result);
			           		if(result == 1)
			           			window.open (global.baseUrl+"\platform/wish-postal-v2/auth?wish_account_id="+id);
			           		else
			           			alert('???????????????');
			            }
			        });
				}
				else
				{
					window.open (global.baseUrl+"\platform/wish-postal-v2/auth?wish_account_id="+id);
				}
			}
		});
	}
	else if(is_used == 1 && code == "lb_chukouyi"){
		//??????v2??????????????????
		var Url = global.baseUrl +'platform/wish-postal-v2/get-url';
		$.ajax({
			type : 'post',
			dataType: 'json',
			data : { wish_account_id:id },
			url: Url,
			success:function(response) 
			{
				if(response['status'] == 0)
				{
					$.ajax({
						type:'get',
						url:response['url'],
			          	dataType: 'json',
			           	xhrFields: {
			           		withCredentials: true
			           	},
			           	success: function(result) 
			           	{
			           		//alert(result);
			           		if(result == 1)
			           			window.open (global.baseUrl+"\carrier/carrier/chukouyi-auth?account_id="+id);
			           		else
			           			alert('???????????????');
			            }
			        });
				}
				else
				{
					window.open (global.baseUrl+"\carrier/carrier/chukouyi-auth?account_id="+id);
				}
			}
		});
	}
	else if(is_used == 1 && code == "lb_4pxNew"){
		//??????v2??????????????????
		var Url = global.baseUrl +'platform/fpx-new/get-url';
		$.ajax({
			type : 'post',
			dataType: 'json',
			data : { '4px_account_id':id },
			url: Url,
			success:function(response) 
			{
				if(response['status'] == 0)
				{
					$.ajax({
						type:'get',
						url:response['url'],
			          	dataType: 'json',
			           	xhrFields: {
			           		withCredentials: true
			           	},
			           	success: function(result) 
			           	{
			           		//alert(result);
			           		if(result == 1)
			           			window.open (global.baseUrl+"platform/fpx-new/auth?4px_account_id="+id);
			           		else
			           			alert('???????????????');
			            }
			        });
				}
				else
				{
					window.open (global.baseUrl+"platform/fpx-new/auth?4px_account_id="+id);
				}
			}
		});
	}
	else
	{
		var Url=global.baseUrl +'configuration/carrierconfig/open-or-close-account';
		$.ajax({
			type : 'post',
			cache : 'false',
			data : {
				aid:id,
				is_used:is_used
			},
			url: Url,
			success:function(response) {
				var res = JSON.parse(response);
				if(res.code == 1){
					$e = $.alert(res.msg,'danger');
				}
				else{
					if(is_used==1)
						$.openModal('/configuration/carrierconfig/editaccount',{id:id,code:code,account:account},'??????????????????','post');
					else{
						$e = $.alert(res.msg,'success');
						window.location=global.baseUrl+'configuration/carrierconfig/index?tab_active='+$('#search_tab_active').val()+'&tcarrier_code='+$('#search_carrier_code').val();
					}
				}
			}
		});
	}
}

//??????????????????
function delAccountNow($id){
	$event = $.confirmBox('????????????????????????????????????????????????');
	$event.then(function(){
		var Url=global.baseUrl +'configuration/carrierconfig/delaccountnow';
		$.ajax({
	        type : 'post',
	        cache : 'false',
	        data : {id:$id},
			url: Url,
	        success:function(response) {
		        $a = $.alert(response,'success');
		        $a.then(function(){
		        	location.reload();
		        })
	        }
	    });
	});
}
//??????????????????
function setDefaultAccount($id,$obj){
	var Url=global.baseUrl +'configuration/carrierconfig/setdefaultaccount';
	$.ajax({
        type : 'post',
        cache : 'false',
        data : {id:$id},
		url: Url,
        success:function(response) {
        	$('.def_account').html('????????????');
        	$('.def_account').attr('style','');
        	$('.def_account').attr('onclick','setDefaultAccount('+$('.def_account').attr('data')+',this)');
        	$('.def_account').attr('class','btn btn-xs');
        	$($obj).html('????????????');
        	$($obj).attr('style','color:#FF9900');
        	$($obj).attr('class','btn btn-xs def_account');
        	$($obj).attr('onclick','');
//	        alert(response);
//	        location.reload();
        }
    });
}
//??????????????????
function setDefaultAddress($id,$obj){
	var Url=global.baseUrl +'configuration/carrierconfig/setdefaultaddress';
	$.ajax({
        type : 'post',
        cache : 'false',
        data : {id:$id},
		url: Url,
        success:function(response) {
        	$('.def_address').html('????????????');
        	$('.def_address').attr('style','');
        	$('.def_address').attr('onclick','setDefaultAddress('+$('.def_address').attr('data')+',this)');
        	$('.def_address').attr('class','btn btn-xs');
        	$($obj).html('????????????');
        	$($obj).attr('style','color:#FF9900');
        	$($obj).attr('class','btn btn-xs def_address');
        	$($obj).attr('onclick','');
        }
    });
}

//???????????????modal
function openCarrierModel($page){
	var Url=global.baseUrl +'configuration/carrierconfig/'+$page;
	$.ajax({
        type : 'get',
        cache : 'false',
        data : {},
		url: Url,
        success:function(response) {
        	$('#myModal').html(response);
        	$('#myModal').modal('show');
        }
    });
};

//??????????????????????????????????????????????????????
function notCarrierDropDownchange(){
	$.ajax({
        type : 'post',
        cache : 'false',
        data : {code:$('[name="notCarrierDropDownid"]').val()},
		url: '/configuration/carrierconfig/newaccount',
		dataType: 'text',
        success:function(response) {
        	$('#add_account_div').html(response);
        }
    });
}

//???????????????????????????
function createAndOpenAccount(){
	carrier_code = $('[name="notCarrierDropDownid"]').val();
	var Url=global.baseUrl +'configuration/carrierconfig/index';
	
	if(carrier_code == '') return false;
	$.maskLayer(true);
	
	if($('[name="accountNickname"]').val() == ''){
		$.alert('????????????????????????!','danger');
		$.maskLayer(false);
		return false;
	}
	
	$('.required').each(function(){
		if($(this).val().trim() == ""){//????????????????????????
			var txt =$(this).parent().prev().find('label').text();
			$.alert(txt+'????????????!','danger');
			$.maskLayer(false);
			return false;
		}
	});

	$.ajax({
        type : 'post',
        cache : 'false',
        data : $('#sys_account_form').serialize(),
        dataType: 'json',
		url: '/configuration/carrierconfig/saveaccount',
        success:function(response) {
        	if(response.success){
//        		window.location.search='carrier_code='+carrier_code;
        		window.location.href=Url+'?tab_active='+$('#search_tab_active').val()+'&tcarrier_code='+carrier_code;
	        }
        	else{
        		$.maskLayer(false);
        		$.alert(response.message,'danger');
	        }
        }
    });
}

//????????????????????????
function loadCarrierList(){
	carrier_code = $('#search_carrier_code').val();

	$.ajax({
        type : 'post',
        cache : 'false',
        data : {
        	carrier_code:carrier_code,
        },
		url: '/configuration/carrierconfig/get-sys-carrier-info',
        success:function(response) {
        	$('#syscarrier_show_div').html(response);
        }
    });
}

//??????????????????
function doshippingaction(obj, val, carrier_code){
	if(val==""){
        bootbox.alert("?????????????????????");return false;
    }
	
	if($('.selectShip:checked').length==0&&val!=''){
    	bootbox.alert("?????????????????????????????????");return $(obj).val('');
    }
	
	idstr='';
	$('.selectShip:checked').each(function(){
		idstr+=','+$(this).val();
	});
	
	switch(val){
		case 'shipping_close':
			$.post('/configuration/carrierconfig/openorcloseshipping',{shippings:idstr},function(result){
				bootbox.alert(result);
				window.location.search='&tcarrier_code='+$('#search_carrier_code').val();
			});
			break;
		case 'shipping_account':
			$.openModal('/configuration/carrierconfig/batch-shipping-carrieraccount-list',{carrier_code:carrier_code, edit_type:'shipping_account'},'????????????????????????','post');
			break;
		case 'shipping_address':
			$.openModal('/configuration/carrierconfig/batch-shipping-carrieraccount-list',{carrier_code:carrier_code, edit_type:'shipping_address'},'????????????????????????','post');
			break;
	}
	
	$(obj).val('');
}

//????????????????????????
function savebatchEditCarrierinfo(edit_type){
	if($('.selectShip:checked').length==0){
    	bootbox.alert("?????????????????????????????????");return $(obj).val('');
    }
	
	if(edit_type == 'shipping_account'){
		common_id = $('select[name=accountID]').val();
		
		if((common_id == '') || (common_id == undefined)){
	    	bootbox.alert("????????????????????????");return $(obj).val('');
	    }
	}
	else if(edit_type == 'shipping_address'){
		common_id = $('select[name=common_address_id]').val();
		
		if((common_id == '') || (common_id == undefined)){
	    	bootbox.alert("????????????????????????");return $(obj).val('');
	    }
	}
	
	idstr='';
	$('.selectShip:checked').each(function(){
		idstr+=','+$(this).val();
	});
	
	$.ajax({
        type : 'post',
        cache : 'false',
        data : {
        	edit_type:edit_type,
        	shippings:idstr,
        	common_id:common_id,
        },
		url: '/configuration/carrierconfig/save-batch-edit-carrierinfo',
        success:function(response) {
        	bootbox.alert(response);
			window.location.search='&tcarrier_code='+$('#search_carrier_code').val();
        }
    });
}

//?????????????????????????????????
function createoreditDeclare(type,id,ck){	
	if(type==3||type==4){
		$tmp=0;
		if(type==3){
			  var r=confirm("??????????????????????????????")
			  if (r==true)
			  {
				  $tmp=1;
			  }
		}
		else
			$tmp=1;
		if($tmp==1){
			var Url=global.baseUrl +'configuration/carrierconfig/save-declare?type='+type;
	
			$.ajax({
		        type : 'post',
		        cache : 'false',
		        data : {ck:ck,cid:id},
		        dataType: 'json',
				url: Url,
		        success:function(response) {
		        	if(response.code == 0){
		        		alert(response.msg);
		        		location.reload();
			        }
		        	else{
		        		alert(response.msg);
			        }
		        }
		    });
		}
	}
	else{
		$str='????????????????????????';
		if(type==1)
			$str='????????????????????????';
		$.modal({
			  url:'/configuration/carrierconfig/createoredit-declare',
			  method:'post',
			  data:{type:type,id:id}
			},$str,{footer:false,inside:false}).done(function($modal){
				$('.iv-btn.btn-default.btn-sm.modal-close').click(function(){$modal.close();});
			}
			);	
	}
}
//????????????????????????
function saveDeclare(type){
	if (! $('#EditFORM').formValidation('form_validate')){
		bootbox.alert(Translator.t('????????????????????????????????????!'));
		return false;
	}
	
	$formdata = $('#EditFORM').serialize();
	var Url=global.baseUrl +'configuration/carrierconfig/save-declare?type='+type;

	$.ajax({
        type : 'post',
        cache : 'false',
        data : $formdata,
        dataType: 'json',
		url: Url,
        success:function(response) {
        	if(response.code == 0){
        		alert(response.msg);
        		location.reload();
	        }
        	else{
        		alert(response.msg);
	        }
        }
    });
}

function initDeclareValidateInput(){
	$("#EditFORM").find('input[type="text"]').formValidation({validType:['trim', 'safeForHtml'],tipPosition:'right'});
	
}

function ResetDeclare(){
	document.getElementById("EditFORM").reset(); 
}

//????????????
function search(type){
	if(type==3){
		$tmp=$('#search3').val().toLocaleLowerCase();
		$div=$('.searchitem3');
	}
	else if(type==2){
		$tmp=$('#search2').val().toLocaleLowerCase();
		$div=$('.searchitem2');
	}
	else{
		$tmp=$('#search').val().toLocaleLowerCase();
		$div=$('.searchitem');
	}
	
	$div.each(function(i){
		$(this).parent().show();
		$(this).parent().next().attr('style','');
		$name=$(this).children().eq(2).html().toLocaleLowerCase();
		if($name.indexOf($tmp)<0 && $tmp!=''){
			$(this).parent().hide();
			$(this).parent().next().attr('style','display:none;');
		}		
	});
}








