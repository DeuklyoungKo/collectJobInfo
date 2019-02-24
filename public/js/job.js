
$(document).ready(function() {

    $('.js-select-language').val($('.js-select-language').data("prevalue"));
    $('.js-select-filter').val($('.js-select-filter').data("prevalue"));


    $('.js-select-state').each(function () {
        console.log($(this).data('prevalue'));
        if ($(this).data('prevalue')) {
            $(this).val($(this).data('prevalue'));
        }
    })


    // change application state start
    $('.js-select-state').on('change', function(e) {

        var optionSelected = $(this).find("option:selected").val();
        var ajaxUrl = $(this).data('url');

        $.ajax({
            url: ajaxUrl,
            data: {
                state : optionSelected
            },
            success: function (data) {
                // var data = jQuery.parseJSON(JSON.stringify(data));
                // console.log(result.result);
                console.log(data['result']);
                showAlert(data['result'], data['resultMent'], data['resultId']);
            }
        });
    });
    // change application state end

    var lastEtc;

    $('.js-etc-textarea').focus(function(e) {
        lastEtc = $(this).val();
    });

    // change application state start
    $('.js-etc-textarea').blur(function(e) {

        var etcValue = $(this).val();
        var ajaxUrl = $(this).data('url');

        if (lastEtc == etcValue) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            data: {
                etcValue : etcValue
            },
            success: function (data) {
                // var data = jQuery.parseJSON(JSON.stringify(data));
                // console.log(result.result);
                showAlert(data['result'], data['resultMent']);
            }
        });
    });
    // change application state end

    function showAlert(title, ment, id) {

        $alertHtml = "    <div class='alert alert-warning alert-dismissible fade show js-result-alert fixed-top' id='alert_"+id+"' role='alert'>\n" +
            "            <strong class='js-title'>"+title+"</strong> <span class='js-ment'> "+ment+"</span>\n" +
            "        <button type='button' class='close' data-dismiss='alert' aria-label='Close'>\n" +
            "            <span aria-hidden='true'>&times;</span>\n" +
            "        </button>\n" +
            "        </div>";

        $('.js-alert-top').append(
            $alertHtml
        );

        setTimeout(function () {
            $('#alert_'+id).alert('close')
        }, 2000)
    }


});
