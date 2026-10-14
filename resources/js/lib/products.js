/**
 * Product names as people read them: the Kiswahili name when the interface
 * is in Kiswahili and the catalog has one, otherwise the catalog name.
 */
export function productName(product, locale) {
    if (!product) return '';
    return locale === 'sw' && product.name_sw ? product.name_sw : product.name;
}

/** The catalog name, when a different (Kiswahili) name is shown first. */
export function secondaryName(product, locale) {
    return locale === 'sw' && product?.name_sw && product.name_sw !== product.name ? product.name : null;
}
