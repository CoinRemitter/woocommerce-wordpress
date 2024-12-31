
// const settings = window.wc.wcSettings.getSetting("coinremitterpayments");
const settings = window.wc.wcSettings.getPaymentMethodData("coinremitterpayments"); 
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__("Pay With Cryptocurrency", "coinremitterpayments");
const des = settings.description;
console.log(des);
// const enabledCurrency = settings.enabledCurrency ? settings.enabledCurrency : "<span class='wallet_error_checkout'>Wallet Not Found. Please Add Wallet</span>";
const enabledCurrency = settings.enabledCurrency;
console.log(settings);
if (settings.enabledCurrency && settings.enabledCurrency.length > 0) {
  wallet_data = settings.enabledCurrency;
} else {
  wallet_data = settings;
}
const htmlToElem = ( html ) => wp.element.RawHTML( { children: html } );

const Block_Gateway = {
  name: "coinremitterpayments",
  label: label,
  content: htmlToElem(wallet_data),
  // content: Object( window.wp.element.createElement )( Content, null ),
  edit: Object(window.wp.element.createElement)(htmlToElem, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
  description: settings.description,
};
window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);

jQuery(document).on('click','.wc-block-components-checkout-place-order-button',function (e) {
  console.log("called");
  return false;
  
})