
jQuery(document).ready(function($) {
    function toggleVisibility(element) {
        var target = $("#" + element.data("target"));
        element.val() === element.data("value") ? target.removeClass("form-control-hidden") : target.addClass("form-control-hidden");
    }

    function toggleWooEventValueOption() {
        $('input[name="pys[core][woo_event_value]"]:checked').val() === "price" ? $(".woo-event-value-option").hide() : $(".woo-event-value-option").show();
    }

    function toggleEddEventValueOption() {
        $('input[name="pys[core][edd_event_value]"]:checked').val() === "price" ? $(".edd-event-value-option").hide() : $(".edd-event-value-option").show();
    }

    function toggleEventTriggersPanel() {
        var eventType = $(".pys_event_trigger_type").val();
        var panelId = "#" + eventType + "_panel";
        $(".event_triggers_panel").hide();
        $(panelId).show();
        eventType === "page_visit" ? $("#url_filter_panel").hide() : $("#url_filter_panel").show();
        var panel = $(panelId);
        var triggerType = panel.data("trigger_type");
        if ($(".event_trigger", panel).length === 0) {
            addEventTrigger(panel, triggerType);
        }
    }

    function addEventTrigger(panel, triggerType) {
        var triggers = $(".event_trigger", panel);
        var newTrigger = $(triggers[0]).clone(true);
        var newTriggerId = $(triggers[triggers.length - 1]).data("trigger_id") + 1;
        var namePrefix = "pys[event][" + triggerType + "_triggers][" + newTriggerId + "]";
        newTrigger.data("trigger_id", newTriggerId);
        $("select", newTrigger).attr("name", namePrefix + "[rule]");
        $("input", newTrigger).attr("name", namePrefix + "[value]");
        newTrigger.css("display", "block");
        newTrigger.insertBefore($(".insert-marker", panel));
    }

    function toggleEventDelay() {
        var eventType = $(".pys_event_trigger_type").val();
        eventType === "page_visit" ? $(".event-delay").css("display", "flex") : $(".event-delay").css("display", "none");
        eventType === "number_page_visit" ? $(".triger_number_page_visit").css("display", "flex") : $(".triger_number_page_visit").css("display", "none");
    }

    function toggleFacebookPanel() {
        $("#pys_event_facebook_enabled").is(":checked") ? $("#facebook_panel").show() : $("#facebook_panel").hide();
    }

    function toggleFacebookCustomEventType() {
        $("#pys_event_facebook_event_type").val() === "CustomEvent" ? $(".facebook-custom-event-type").css("visibility", "visible") : $(".facebook-custom-event-type").css("visibility", "hidden");
    }

    function toggleFacebookParamsPanel() {
        $("#pys_event_facebook_params_enabled").is(":checked") ? $("#facebook_params_panel").show() : $("#facebook_params_panel").hide();
    }

    function updateFacebookParamsPanelClass() {
        var eventType = $("#pys_event_facebook_event_type").val();
        $("#facebook_params_panel").removeClass().addClass(eventType);
    }

    function toggleFacebookCustomCurrency() {
        $("#pys_event_facebook_params_currency").val() === "custom" ? $(".facebook-custom-currency").css("visibility", "visible") : $(".facebook-custom-currency").css("visibility", "hidden");
    }

    function togglePinterestPanel() {
        $("#pys_event_pinterest_enabled").is(":checked") ? $("#pinterest_panel").show() : $("#pinterest_panel").hide();
    }

    function togglePinterestCustomEventType() {
        $("#pys_event_pinterest_event_type").val() === "CustomEvent" ? $(".pinterest-custom-event-type").css("visibility", "visible") : $(".pinterest-custom-event-type").css("visibility", "hidden");
    }

    function togglePinterestParamsPanel() {
        $("#pys_event_pinterest_params_enabled").is(":checked") ? $("#pinterest_params_panel").show() : $("#pinterest_params_panel").hide();
    }

    function updatePinterestParamsPanelClass() {
        var eventType = $("#pys_event_pinterest_event_type").val();
        $("#pinterest_params_panel").removeClass().addClass(eventType);
    }

    function togglePinterestCustomCurrency() {
        $("#pys_event_pinterest_params_currency").val() === "custom" ? $(".pinterest-custom-currency").css("visibility", "visible") : $(".pinterest-custom-currency").css("visibility", "hidden");
    }

    function toggleAnalyticsPanel() {
        $("#pys_event_ga_ads_enabled").is(":checked") ? $("#analytics_panel").show() : $("#analytics_panel").hide();
    }

    function toggleGaAdsCustomAction() {
        var eventAction = $("#pys_event_ga_ads_event_action").val();
        eventAction === "_custom" || eventAction === "CustomEvent" ? $("#ga-ads-custom-action").css("visibility", "visible") : $("#ga-ads-custom-action").css("visibility", "hidden");
    }

    function toggleBingPanel() {
        $("#pys_event_bing_enabled").is(":checked") ? $("#bing_panel").show() : $("#bing_panel").hide();
    }

    $(function() {
        $('[data-toggle="pys-popover"]').popover({
            container: "#pys",
            html: true,
            content: function() {
                return $("#pys-" + $(this).data("popover_id")).html();
            }
        });
    });

    $(".pys-pysselect2").pysselect2();
    $(".pys-tags-pysselect2").pysselect2({
        tags: true,
        tokenSeparators: [",", " "]
    });

    $("select.controls-visibility").on("change", function(e) {
        toggleVisibility($(this));
    }).each(function(e, t) {
        toggleVisibility($(t));
    });

    $(".card-collapse").on("click", function() {
        var cardBody = $(this).closest(".card").children(".card-body");
        cardBody.hasClass("show") ? cardBody.hide().removeClass("show") : cardBody.show().addClass("show");
    });

    $(".collapse-control .custom-switch-input").on('change', function() {
        var target = $("." + $(this).data("target"));
        target.length > 0 && ($(this).prop("checked") ? target.show() : target.hide());
    }).trigger("change");

    toggleWooEventValueOption();
    $('input[name="pys[core][woo_event_value]"]').on('change', function() {
        toggleWooEventValueOption();
    });

    toggleEddEventValueOption();
    $('input[name="pys[core][edd_event_value]"]').on('change', function() {
        toggleEddEventValueOption();
    });

    $("#pys_select_all_events").on('change', function() {
        $(this).prop("checked") ? $(".pys-select-event").prop("checked", "checked") : $(".pys-select-event").prop("checked", false);
    });

    toggleEventDelay();
    toggleEventTriggersPanel();
    $(".pys_event_trigger_type").on('change', function() {
        toggleEventDelay();
        toggleEventTriggersPanel();
    });

    $(".add-event-trigger").on("click", function() {
        var panel = $(this).closest(".event_triggers_panel");
        addEventTrigger(panel, panel.data("trigger_type"));
    });

    $(".remove-row").on("click", function(e) {
        $(this).closest(".row.event_trigger, .row.facebook-custom-param, .row.pinterest-custom-param").remove();
    });

    toggleFacebookPanel();
    toggleFacebookCustomEventType();
    toggleFacebookParamsPanel();
    updateFacebookParamsPanelClass();
    toggleFacebookCustomCurrency();

    $("#pys_event_facebook_enabled").on("click", function() {
        toggleFacebookPanel();
    });

    $("#pys_event_facebook_event_type").on('change', function() {
        toggleFacebookCustomEventType();
        updateFacebookParamsPanelClass();
    });

    $("#pys_event_facebook_params_enabled").on("click", function() {
        toggleFacebookParamsPanel();
    });

    $("#pys_event_facebook_params_currency").on('change', function() {
        toggleFacebookCustomCurrency();
    });

    $(".add-facebook-parameter").on("click", function() {
        var panel = $("#facebook_params_panel");
        var params = $(".facebook-custom-param", panel);
        var newParam = $(params[0]).clone(true);
        var newParamId = $(params[params.length - 1]).data("param_id") + 1;
        var namePrefix = "pys[event][facebook_custom_params][" + newParamId + "]";
        newParam.data("param_id", newParamId);
        $("input.custom-param-name", newParam).attr("name", namePrefix + "[name]");
        $("input.custom-param-value", newParam).attr("name", namePrefix + "[value]");
        newParam.css("display", "flex");
        newParam.insertBefore($(".insert-marker", panel));
    });

    togglePinterestPanel();
    togglePinterestCustomEventType();
    togglePinterestParamsPanel();
    updatePinterestParamsPanelClass();
    togglePinterestCustomCurrency();

    $("#pys_event_pinterest_enabled").on("click", function() {
        togglePinterestPanel();
    });

    $("#pys_event_pinterest_event_type").on('change', function() {
        togglePinterestCustomEventType();
        updatePinterestParamsPanelClass();
    });

    $("#pys_event_pinterest_params_enabled").on("click", function() {
        togglePinterestParamsPanel();
    });

    $("#pys_event_pinterest_params_currency").on('change', function() {
        togglePinterestCustomCurrency();
    });

    $(".add-pinterest-parameter").on("click", function() {
        var panel = $("#pinterest_params_panel");
        var params = $(".pinterest-custom-param", panel);
        var newParam = $(params[0]).clone(true);
        var newParamId = $(params[params.length - 1]).data("param_id") + 1;
        var namePrefix = "pys[event][pinterest_custom_params][" + newParamId + "]";
        newParam.data("param_id", newParamId);
        $("input.custom-param-name", newParam).attr("name", namePrefix + "[name]");
        $("input.custom-param-value", newParam).attr("name", namePrefix + "[value]");
        newParam.css("display", "flex");
        newParam.insertBefore($(".insert-marker", panel));
    });

    toggleAnalyticsPanel();
    toggleGaAdsCustomAction();

    $("#pys_event_ga_ads_enabled").on("click", function() {
        toggleAnalyticsPanel();
    });

    $("#pys_event_ga_ads_event_action").on('change', function() {
        toggleGaAdsCustomAction();
    });

    toggleBingPanel();

    $("#pys_event_bing_enabled").on("click", function() {
        toggleBingPanel();
    });
});
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
            $("#url_filter_panel").hide();
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

    function cloneEventCondition() {
        let cloned = $( '#pys_add_event_condition .condition_group' ).clone( true ),
            conditionWrapper = $( '.pys_conditions_wrapper' ),
            conditionGroup = $( '.pys_conditions_wrapper .condition_group' ),
            conditionId = 0;

        if ( conditionGroup.length > 0 ) {
            conditionId = parseInt( $( conditionGroup[ conditionGroup.length - 1 ] ).attr( "data-condition_id" ) ) + 1;
        }
        $( '.pys_event_condition_type', cloned ).attr( {
            name: 'pys[event][conditions][' + conditionId + '][condition_type]',
            id: 'pys_event_' + conditionId + '_condition_type',
            value: 'url_filters'
        } );


        cloned.attr( 'data-condition_id', conditionId );
        cloned.css( 'display', 'block' );

        conditionWrapper.append( cloned );
        $( '.pys_event_condition_type', cloned ).trigger( 'change' );
    }

    function checkConditionTypeAvailability( group, triggerPanel ) {
        let panelAvailability = group.find( '.' + triggerPanel + '_panel' );
        group.find( '.event_conditions_panel' ).hide();
        if ( panelAvailability.length === 0 ) {
            let clonedCondition = $( '#pys_add_event_condition .' + triggerPanel + '_panel' ).clone( true ),
                conditionId = group.attr( "data-condition_id" );
            switch ( triggerPanel ) {
                case 'url_filters':
                case 'url_parameters':
                case 'landing_page':
                case 'source':
                    $( 'select', clonedCondition ).attr( {
                        name: 'pys[event][conditions][' + conditionId + '][' + triggerPanel + '][condition_rule]',
                        id: 'pys_event_' + conditionId + '_'+ triggerPanel + '_condition_rule'
                    } );
                    $( 'input[type="text"]', clonedCondition ).attr( {
                        name: 'pys[event][conditions][' + conditionId + '][' + triggerPanel + '][condition_value]',
                        id: 'pys_event_' + conditionId + '_'+ triggerPanel + '_condition_value'
                    } );
                    break;
                case 'device' :
                    $( '.custom-radio input[type="radio"]', clonedCondition ).attr( {
                        name: 'pys[event][conditions][' + conditionId + '][device]',
                        id: 'pys_event_' + conditionId + '_device'
                    } );
                    break;
                case 'user_role':
                    $( 'select', clonedCondition ).attr( {
                        name: 'pys[event][conditions][' + conditionId + '][user_role][]',
                        id: 'pys_event_' + conditionId + '_user_role'
                    } );
                    $( 'input[type="hidden"]', clonedCondition ).attr( {
                        name: 'pys[event][conditions][' + conditionId + '][user_role][]',
                    } );
                    break;
            }

            clonedCondition.show();
            let inserted = group.append(clonedCondition);
            $( '.pys-role-pysselect2', inserted ).each( function ( index, item ) {
                $(this).pysselect2()
            });
        } else {
            panelAvailability.show();
        }
    }

    $('.pys_conditions_wrapper .pys-role-pysselect2').each( function ( index, value ) {
        $(this).pysselect2();
    } );
    $( ".pys_event_condition_type" ).on( 'change', function () {
        let conditionGroup = $( this ).closest( '.condition_group' ),
            panel = $( this ).val();
        checkConditionTypeAvailability( conditionGroup, panel );
    } );

    $( '#pys-add-condition .add-condition' ).on( 'click', function ( e ) {
        e.preventDefault();
    } );

    $( document ).on( 'click', '.pys_conditions_wrapper .remove-row', function () {
        $( this ).closest( '.condition_group' ).remove();
    } )

    if ( $( '.pys_conditions_wrapper .condition_group' ).length === 0 ) {
        cloneEventCondition();
    }
});

