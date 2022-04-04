BX.ready(function () {

  const phoneDiv = $('#phone_div');
  const phoneField = $('input[name="UF_PHONE"]');
  const FOUND_USER = 'Найден:';

  $('#findbyphone').on('click', function () {
    const phone = $('input[name=\'UF_PHONE\']').val();
    BX.ajax.runAction('adv:communicationcards.api.updater.apply', {
      data: {
        phone: phone,
      },
    }).then(function (data) {
      printOutData(data);
    });
  });

  $(phoneField).on('focusout', function () {
    let val = $(this).val();
    $(this).val(val.replace(/[^0-9]/g, ''));
  });

  function printOutData(result) {
    let userInfo,
        userLink;

    if (result.status !== 'success') {
      console.error(result.errors);
    }

    if (result.data && result.data.userdata) {
      userInfo = JSON.parse(result.data.userdata);
    }

    phoneDiv.empty().append('Данных не найдено');
    if (!$.isEmptyObject(userInfo)) {
      userLink = '<span><b>'
          + FOUND_USER + '</b> <a target="_blank" href="/bitrix/admin/sale_buyers_profile.php?USER_ID='
          + userInfo.ID + '&lang=ru">'
          + (userInfo.NAME ? userInfo.NAME + ' ' : '')
          + (userInfo.SECOND_NAME ? userInfo.SECOND_NAME + ' ' : '')
          + (userInfo.LAST_NAME ? userInfo.LAST_NAME + ' ' : '')
          + '</a></span>';

      phoneDiv.empty().html(userLink);
    }
  }
});
