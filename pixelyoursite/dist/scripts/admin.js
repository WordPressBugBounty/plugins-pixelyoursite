
jQuery(document).ready(function(c){function n(e){var t=c("#"+e.data("target"));e.val()===e.data("value")?t.removeClass("form-control-hidden"):t.addClass("form-control-hidden")}function e(){"price"===c('input[name="pys[core][woo_event_value]"]:checked').val()?c(".woo-event-value-option").hide():c(".woo-event-value-option").show()}function t(){"price"===c('input[name="pys[core][edd_event_value]"]:checked').val()?c(".edd-event-value-option").hide():c(".edd-event-value-option").show()}function a(){var e=c(".pys_event_trigger_type").val(),t="#"+e+"_panel";c(".event_triggers_panel").hide(),c(t).show(),"page_visit"===e?c("#url_filter_panel").hide():c("#url_filter_panel").show();var n=c(t),a=n.data("trigger_type");0===c(".event_trigger",n).length-1&&s(n,a)}function s(e,t){var n=c(".event_trigger",e),a=c(n[0]).clone(!0),s=c(n[n.length-1]).data("trigger_id")+1,i="pys[event]["+t+"_triggers]["+s+"]";a.data("trigger_id",s),c("select",a).attr("name",i+"[rule]"),c("input",a).attr("name",i+"[value]"),a.css("display","block"),a.insertBefore(c(".insert-marker",e))}function i(){"page_visit"===c(".pys_event_trigger_type").val()?c(".event-delay").css("display","flex"):c(".event-delay").css("display","none"); "number_page_visit"===c(".pys_event_trigger_type").val()?c(".triger_number_page_visit").css("display","flex"):c(".triger_number_page_visit").css("display","none")}function o(){c("#pys_event_facebook_enabled").is(":checked")?c("#facebook_panel").show():c("#facebook_panel").hide()}function r(){"CustomEvent"===c("#pys_event_facebook_event_type").val()?c(".facebook-custom-event-type").css("visibility","visible"):c(".facebook-custom-event-type").css("visibility","hidden")}function p(){c("#pys_event_facebook_params_enabled").is(":checked")?c("#facebook_params_panel").show():c("#facebook_params_panel").hide()}function _(){var e=c("#pys_event_facebook_event_type").val();c("#facebook_params_panel").removeClass().addClass(e)}function l(){"custom"===c("#pys_event_facebook_params_currency").val()?c(".facebook-custom-currency").css("visibility","visible"):c(".facebook-custom-currency").css("visibility","hidden")}function v(){c("#pys_event_pinterest_enabled").is(":checked")?c("#pinterest_panel").show():c("#pinterest_panel").hide()}function u(){"CustomEvent"===c("#pys_event_pinterest_event_type").val()?c(".pinterest-custom-event-type").css("visibility","visible"):c(".pinterest-custom-event-type").css("visibility","hidden")}function d(){c("#pys_event_pinterest_params_enabled").is(":checked")?c("#pinterest_params_panel").show():c("#pinterest_params_panel").hide()}function y(){var e=c("#pys_event_pinterest_event_type").val();c("#pinterest_params_panel").removeClass().addClass(e)}function m(){"custom"===c("#pys_event_pinterest_params_currency").val()?c(".pinterest-custom-currency").css("visibility","visible"):c(".pinterest-custom-currency").css("visibility","hidden")}function f(){c("#pys_event_ga_ads_enabled").is(":checked")?c("#analytics_panel").show():c("#analytics_panel").hide()}function h(){"_custom"===c("#pys_event_ga_ads_event_action").val() || "CustomEvent"===c("#pys_event_ga_ads_event_action").val()?c("#ga-ads-custom-action").css("visibility","visible"):c("#ga-ads-custom-action").css("visibility","hidden")}function g(){c("#pys_event_bing_enabled").is(":checked")?c("#bing_panel").show():c("#bing_panel").hide()}c(function(){c('[data-toggle="pys-popover"]').popover({container:"#pys",html:!0,content:function(){return c("#pys-"+c(this).data("popover_id")).html()}})}),c(".pys-pysselect2").pysselect2(),c(".pys-tags-pysselect2").pysselect2({tags:!0,tokenSeparators:[","," "]}),c("select.controls-visibility").on("change",function(e){n(c(this))}).each(function(e,t){n(c(t))}),c(".card-collapse").on("click",function(){var e=c(this).closest(".card").children(".card-body");e.hasClass("show")?e.hide().removeClass("show"):e.show().addClass("show")}),c(".collapse-control .custom-switch-input").on('change',function(){var e=c(this),t=c("."+e.data("target"));0<t.length&&(e.prop("checked")?t.show():t.hide())}).trigger("change"),e(),c('input[name="pys[core][woo_event_value]"]').on('change',function(){e()}),t(),c('input[name="pys[core][edd_event_value]"]').on('change',function(){t()}),c("#pys_select_all_events").on('change',function(){c(this).prop("checked")?c(".pys-select-event").prop("checked","checked"):c(".pys-select-event").prop("checked",!1)}),i(),a(),c(".pys_event_trigger_type").on('change',function(){i(),a()}),c(".add-event-trigger").on("click",function(){var e=c(this).closest(".event_triggers_panel");s(e,e.data("trigger_type"))}),c(".remove-row").on("click",function(e){c(this).closest(".row.event_trigger, .row.facebook-custom-param, .row.pinterest-custom-param").remove()}),o(),r(),p(),_(),l(),c("#pys_event_facebook_enabled").on("click",function(){o()}),c("#pys_event_facebook_event_type").on('change',function(){r(),_()}),c("#pys_event_facebook_params_enabled").on("click",function(){p()}),c("#pys_event_facebook_params_currency").on('change',function(){l()}),c(".add-facebook-parameter").on("click",function(){var e=c("#facebook_params_panel"),t=c(".facebook-custom-param",e),n=c(t[0]).clone(!0),a=c(t[t.length-1]).data("param_id")+1,s="pys[event][facebook_custom_params]["+a+"]";n.data("param_id",a),c("input.custom-param-name",n).attr("name",s+"[name]"),c("input.custom-param-value",n).attr("name",s+"[value]"),n.css("display","flex"),n.insertBefore(c(".insert-marker",e))}),v(),u(),d(),y(),m(),c("#pys_event_pinterest_enabled").on("click",function(){v()}),c("#pys_event_pinterest_event_type").on('change',function(){u(),y()}),c("#pys_event_pinterest_params_enabled").on("click",function(){d()}),c("#pys_event_pinterest_params_currency").on('change',function(){m()}),c(".add-pinterest-parameter").on("click",function(){var e=c("#pinterest_params_panel"),t=c(".pinterest-custom-param",e),n=c(t[0]).clone(!0),a=c(t[t.length-1]).data("param_id")+1,s="pys[event][pinterest_custom_params]["+a+"]";n.data("param_id",a),c("input.custom-param-name",n).attr("name",s+"[name]"),c("input.custom-param-value",n).attr("name",s+"[value]"),n.css("display","flex"),n.insertBefore(c(".insert-marker",e))}),f(),h(),c("#pys_event_ga_ads_enabled").on("click",function(){f()}),c("#pys_event_ga_ads_event_action").on('change',function(){h()}),g(),c("#pys_event_bing_enabled").on("click",function(){g()})});

jQuery( document ).ready(function($) {

    function enable_gtm(){
        $("#pys_event_gtm_enabled").is(":checked")?$("#gtm_panel").show():$("#gtm_panel").hide()
    }
    enable_gtm();
    $("#pys_event_gtm_enabled").on('click',function(){enable_gtm()})


    updateFDPValue($("#pys_facebook_fdp_purchase_event_fire"));
    $("#pys_facebook_fdp_purchase_event_fire").on('change',function () {

        updateFDPValue(this);
    });
    updatePostEventFields();
    $(".pys_event_trigger_type").on('change',function(){
        updatePostEventFields();
    });

    $("#pys_event_ga_ads_event_action").on('change',function () {
        var value = $(this).val();
        $(".ga-ads-custom-param-list").html("");
        $(".ga-ads-param-list").html("");

        for(i=0;i<ga_fields.length;i++){
            if(ga_fields[i].name == value) {
                ga_fields[i].fields.forEach(function(el){
                    $(".ga-ads-param-list").append('<div class="row mb-3 ga_ads_param">\n' +
                        '<label class="col-5 control-label">'+el+'</label>' +
                        '<div class="col-4">' +
                        '<input type="text" name="pys[event][ga_ads_params]['+el+']" class="form-control">' +
                        '</div>' +
                        ' </div>');
                });
                break;
            }
        }

        if($('option:selected', this).attr('group') == "Retail/Ecommerce") {
            $(".ga_woo_info").attr('style',"display: block");
        } else {
            $(".ga_woo_info").attr('style',"display: none");
        }
        updateGAActionSelector();
    })

    if($(".action_merged_g4").length > 0) {
        var value = $('.action_merged_g4').val();
        if($(".ga-ads-param-list .ga_ads_param").length == 0) {
            for(i=0;i<ga_fields.length;i++){
                if(ga_fields[i].name == value) {
                    ga_fields[i].fields.forEach(function(el){
                        $(".ga-ads-param-list").append('<div class="row mb-3 ga_ads_param">\n' +
                            '<label class="col-5 control-label">'+el+'</label>' +
                            '<div class="col-4">' +
                            '<input type="text" name="pys[event][ga_ads_params]['+el+']" class="form-control">' +
                            '</div>' +
                            ' </div>');
                    });
                    break;
                }
            }
        };



        if($('option:selected', this).attr('group') == "Retail/Ecommerce") {
            $(".ga_woo_info").attr('style',"display: block");
        } else {
            $(".ga_woo_info").attr('style',"display: none");
        }
    };
    updateGAActionSelector();
    function updateGAActionSelector() {
        if($('.action_merged_g4').length > 0) {
            if($('.action_merged_g4').val() === "_custom" || $('.action_merged_g4').val() === "CustomEvent") {
                $('#ga-ads-custom-action_g4').css('display','block');
            } else {
                $('#ga-ads-custom-action_g4').css('display','none')
            }
        } else {
            $('#ga-ads-custom-action_g4').css('display','none')
        }
    }
    $('.ga-ads-custom-param-list').on("click",'.ga-ads-custom-param .remove-row',function(){
        $(this).parents('.ga-ads-custom-param').remove();
    });

    $('.add-ga-ads-custom-parameter').on("click",function(){
        var index = $(".ga-ads-custom-param-list .ga-ads-custom-param").length + 1;
        $(".ga-ads-custom-param-list").append('<div class="row mt-3 ga-ads-custom-param" data-param_id="'+index+'">' +
            '<div class="col">' +
            '<div class="row">' +
            '<div class="col-1"></div>' +
            '<div class="col-4">' +
            '<input type="text" placeholder="Enter name" class="form-control custom-param-name"' +
            ' name="pys[event][ga_ads_custom_params]['+index+'][name]"' +
            ' value="">' +
            '</div>' +
            '<div class="col-4">' +
            '<input type="text" placeholder="Enter value" class="form-control custom-param-value"' +
            ' name="pys[event][ga_ads_custom_params]['+index+'][value]"' +
            ' value="">' +
            '</div>' +
            '<div class="col-2">' +
            '<button type="button" class="btn btn-sm remove-row">' +
            '<i class="fa fa-trash-o" aria-hidden="true"></i>' +
            '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>');
    });

    $(".action_gtm").on('change',function () {
        var value = $(this).val();
        $(".gtm-param-list").html("");

        for(i=0;i<gtm_fields.length;i++){
            if(gtm_fields[i].name == value) {
                gtm_fields[i].fields.forEach(function(el){
                    $(".gtm-param-list").append('<div class="row mb-3 gtm_param">\n' +
                        '<label class="col-5 control-label">'+el+'</label>' +
                        '<div class="col-4">' +
                        '<input type="text" name="pys[event][gtm_params]['+el+']" class="form-control">' +
                        '</div>' +
                        ' </div>');
                });
                break;
            }
        }

        if($('option:selected', this).attr('group') == "Retail/Ecommerce") {
            $(".gtm_woo_info").attr('style',"display: block");
        } else {
            $(".gtm_woo_info").attr('style',"display: none");
        }
        updateGTMActionSelector();
    })

    if($(".action_gtm").length > 0) {
        var value = $('.action_gtm').val();
        if($(".gtm-param-list .gtm_param").length == 0) {
            for(i=0;i<gtm_fields.length;i++){
                if(gtm_fields[i].name == value) {
                    gtm_fields[i].fields.forEach(function(el){
                        $(".gtm-param-list").append('<div class="row mb-3 gtm_param">\n' +
                            '<label class="col-5 control-label">'+el+'</label>' +
                            '<div class="col-4">' +
                            '<input type="text" name="pys[event][gtm_params]['+el+']" class="form-control">' +
                            '</div>' +
                            ' </div>');
                    });
                    break;
                }
            }
        };



        if($('option:selected', this).attr('group') == "Retail/Ecommerce") {
            $(".gtm_woo_info").attr('style',"display: block");
        } else {
            $(".gtm_woo_info").attr('style',"display: none");
        }
        updateGTMActionSelector();
    };


    function updateGTMActionSelector() {
        if($('.action_gtm').length > 0) {
            if($('.action_gtm').val() === "_custom" || $('.action_gtm').val() === "CustomEvent") {
                $('#gtm-custom-action_g4').css('display','block');
            } else {
                $('#gtm-custom-action_g4').css('display','none')
            }
        }
    }

    $('.gtm-custom-param-list').on("click",'.gtm-custom-param .remove-row',function(){
        var currentCount = $(".gtm-custom-param-list .gtm-custom-param").length;
        var messageContainer = $("#custom-param-message");
        $(this).parents('.gtm-custom-param').remove();
        if (messageContainer.length && $(".gtm-custom-param-list .gtm-custom-param").length < 5) {
            messageContainer.remove();
        }
    });
    $('.add-gtm-custom-parameter').on('click',function(){
        var currentCount = $(".gtm-custom-param-list .gtm-custom-param").length;
        var messageContainer = $("#custom-param-message");
        if (currentCount < 5) {
            var index = currentCount + 1;
            $(".gtm-custom-param-list").append('<div class="row mt-3 gtm-custom-param" data-param_id="'+index+'">' +
                '<div class="col">' +
                '<div class="row">' +
                '<div class="col-1"></div>' +
                '<div class="col-4">' +
                '<input type="text" placeholder="Enter name" class="form-control custom-param-name"' +
                ' name="pys[event][gtm_custom_params]['+index+'][name]"' +
                ' value="">' +
                '</div>' +
                '<div class="col-4">' +
                '<input type="text" placeholder="Enter value" class="form-control custom-param-value"' +
                ' name="pys[event][gtm_custom_params]['+index+'][value]"' +
                ' value="">' +
                '</div>' +
                '<div class="col-2">' +
                '<button type="button" class="btn btn-sm remove-row">' +
                '<i class="fa fa-trash-o" aria-hidden="true"></i>' +
                '</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>');
            if (messageContainer.length) {
                messageContainer.remove();
            }
        } else {
            if (messageContainer.length) {
                messageContainer.text("You can add up to 5 custom parameters only.");
            } else {
                $(".gtm-custom-param-list").after('<p id="custom-param-message" style="color: red;">You can add up to 5 custom parameters only.</p>');
            }
        }

    });



    function updateFDPValue(input) {
        if($(input).val() == "scroll_pos") {
            $("#fdp_purchase_event_fire_scroll_block").show();
            $("#pys_facebook_fdp_purchase_event_fire_css").hide()
        } else  if($(input).val() == "css_click") {
            $("#fdp_purchase_event_fire_scroll_block").hide();
            $("#pys_facebook_fdp_purchase_event_fire_css").show()
        } else {
            $("#fdp_purchase_event_fire_scroll_block").hide();
            $("#pys_facebook_fdp_purchase_event_fire_css").hide()
        }
    }

    function updatePostEventFields() {
        $("#fire_event_once").show();
        $(".triger_number_page_visit").css("display","none");
        if($(".pys_event_trigger_type").val() == "post_type") {
            $(".event-delay").css("display","flex");
            $(".triger_post_type").show();
            $("#url_filter_panel").hide();
            $("#embedded_video_view").hide();
            $("#elementor_form").hide();
        } else if($(".pys_event_trigger_type").val() == "number_page_visit") {
            $(".triger_number_page_visit").css("display","flex");
            $("#url_filter_panel").hide();
            $(".post_type_error").hide();
            $(".triger_post_type").hide();
            $("#fire_event_once").hide();
            $("#embedded_video_view").hide();
            $("#elementor_form").hide();
        } else if($(".pys_event_trigger_type").val() == "home_page") {
            $(".event-delay").css("display","flex");
            $("#url_filter_panel").hide();
            $(".post_type_error").hide();
            $(".triger_post_type").hide();
            $("#embedded_video_view").hide();
            $("#elementor_form").hide();
        } else if($(".pys_event_trigger_type").val() == "elementor_form") {
            $("#url_filter_panel").hide();
            $(".post_type_error").hide();
            $(".triger_post_type").hide();
            $("#embedded_video_view").hide();
            $("#elementor_form").show();
        } else if($(".pys_event_trigger_type").val() == "video_view") {
            $("#url_filter_panel").hide();
            $(".post_type_error").hide();
            $(".triger_post_type").hide();
            $("#embedded_video_view").show();
            $("#elementor_form").hide();
        } else if($(".pys_event_trigger_type.pys_event_trigger_type").val() == "email_link") {
            $("#url_filter_panel").hide();
            $(".post_type_error").hide();
            $(".triger_post_type").hide();
            $("#embedded_video_view").hide();
            $("#elementor_form").hide();
        } else {
            $(".triger_post_type").hide();
            $("#embedded_video_view").hide();
            $("#elementor_form").hide();
        }
    }

    $("#pys_core_automatic_events_enabled").on("change",function () {
        var $headSwitch = $(this).parents(".card-header").find(".card-collapse")
        var $body = $(this).parents(".card").children(".card-body")
        if($(this).is(':checked')) {
            $headSwitch.css("display","block")
        } else {
            $body.removeClass("show")
            $body.css("display","none")
            $headSwitch.css("display","none")
        }

    })
    $("#pys .pys_utm_builder .utm, #pys .pys_utm_builder .site_url").on("input",function () {

        updateBuilderUrl()
    })
    $("#pys .copy_text").on("click",function () {

        navigator.clipboard.writeText($(this).text());
    })
    function updateBuilderUrl() {
        let utms = ""
        let urls =  $("#pys .pys_utm_builder .site_url").val()
        $("#pys .pys_utm_builder .utm").each(function () {
            if($(this).val() != "") {
                if(utms === "") {
                    utms = $(this).data('type')+"="+$(this).val()
                }else {
                    utms += "&"+$(this).data('type')+"="+$(this).val()
                }
            }
        })
        if(utms!="") {
            utms = "?"+utms
        }
        $("#pys .build_utms_with_url").text(urls+utms)
        $("#pys .build_utms").text(utms)
    }

    updateBuilderUrl()

    const inputWrapperList = document.getElementsByClassName('input-number-wrapper');

    for(let wrapper of inputWrapperList) {
        const input = wrapper.querySelector('input');
        const incrementation = +input.step || 1;

        wrapper.querySelector('.increase').addEventListener('click', function(e) {
            e.preventDefault();
            incInputNumber(input, incrementation);
        });

        wrapper.querySelector('.decrease').addEventListener('click', function(e) {
            e.preventDefault();
            incInputNumber(input, "-" + incrementation);
        });
    }

    function incInputNumber(input, step) {
        if(!input.disabled) {
            let val = +input.value;

            if (isNaN(val)) val = 0
            val += +step;

            if(input.max && val > input.max) {
                val = input.max;
            } else if (input.min && val < input.min) {
                val = input.min;
            } else if (val < 0) {
                val = 0;
            }

            input.value = val;
            input.setAttribute("value", val);
        }
    }

    $('#pys_event_title').on( "change", function ( e ) {
        const value = $(this).val();
        $.ajax( {
            url: ajaxurl,
            data: {
                action: 'get_transform_title',
                _wpnonce: $( '#get_transform_title_wpnonce' ).val(),
                title: value
            },
            type: 'POST',
            success: function ( data ) {
                $('#manual_custom_object_name').text(data.data.title);
                $('input#pys_event_gtm_custom_object_name').val(data.data.title);
            }, error: function ( data ) {
                console.log( data );
            }
        } );

    });

    $('input#pys_event_gtm_custom_object_name').on( "input", function ( e ) {
        const value = $(this).val();
        $('#manual_custom_object_name').text(value);
    });
});

