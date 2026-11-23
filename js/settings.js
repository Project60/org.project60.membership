(function () {
  'use strict';

  const PREFIX = 'p60_membership_';
  const SYNCHRONISE_NOW = 'synchronise_payment_now';
  const PAID_VIA_DEPENDENT = [
    'annual_amount_field',
    'installment_amount_field',
    'diff_amount_field',
    'payment_frequency_field',
    'payment_type_field',
    'payment_type_field_mapping',
    'missing_period_amount_field',
  ].map((name) => PREFIX + name).concat([SYNCHRONISE_NOW]);
  const PAYMENT_TYPE_DEPENDENT = [PREFIX + 'payment_type_field_mapping', SYNCHRONISE_NOW];

  function init() {
    const form = document.querySelector('.crm-setting-block-membership');
    if (!form) {
      return;
    }
    const paidVia = form.querySelector('#' + PREFIX + 'paid_via_field');
    const paymentType = form.querySelector('#' + PREFIX + 'payment_type_field');
    if (!(paidVia instanceof HTMLSelectElement) || !(paymentType instanceof HTMLSelectElement)) {
      return;
    }

    const setVisible = (names, visible) => {
      names.forEach((name) => {
        const row = form.querySelector('tr.crm-setting-form-block-' + name);
        if (row instanceof HTMLElement) {
          row.style.display = visible ? '' : 'none';
        }
      });
    };

    const update = () => {
      const hasPaidVia = paidVia.value !== '';
      setVisible(PAID_VIA_DEPENDENT, hasPaidVia);
      if (hasPaidVia) {
        setVisible(PAYMENT_TYPE_DEPENDENT, paymentType.value !== '');
      }
    };

    paidVia.addEventListener('change', update);
    paymentType.addEventListener('change', update);
    update();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  }
  else {
    init();
  }
})();
