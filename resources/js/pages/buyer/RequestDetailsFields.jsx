import { SelectField, TextAreaField, TextField } from '../../components/ui';
import { useI18n } from '../../i18n';
import { REGION_NAMES, regionLabel } from '../../location/regions';

/** Title, notes and delivery fields shared by create and edit. */
export default function RequestDetailsFields({ form, setForm, errors = {} }) {
    const { t, locale } = useI18n();
    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });
    const today = new Date().toISOString().slice(0, 10);

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <TextField
                className="sm:col-span-2"
                label={t('buyer.fieldTitle')}
                hint={t('buyer.fieldTitleHint')}
                placeholder={t('buyer.fieldTitlePlaceholder')}
                required
                maxLength={255}
                value={form.title}
                onChange={set('title')}
                error={errors.title}
            />
            <SelectField label={t('buyer.fieldRegion')} optional value={form.delivery_region ?? ''} onChange={set('delivery_region')} error={errors.delivery_region}>
                <option value="">{t('buyer.chooseRegion')}</option>
                {REGION_NAMES.map((region) => (
                    <option key={region} value={region}>
                        {regionLabel(region, locale)}
                    </option>
                ))}
            </SelectField>
            <TextField
                label={t('buyer.fieldNeededBy')}
                optional
                type="date"
                min={today}
                value={form.needed_by ?? ''}
                onChange={set('needed_by')}
                error={errors.needed_by}
            />
            <TextField
                className="sm:col-span-2"
                label={t('buyer.fieldAddress')}
                optional
                placeholder={t('buyer.fieldAddressPlaceholder')}
                maxLength={500}
                value={form.delivery_address ?? ''}
                onChange={set('delivery_address')}
                error={errors.delivery_address}
            />
            <TextAreaField
                className="sm:col-span-2"
                label={t('product.notesForSellers')}
                optional
                placeholder={t('buyer.fieldNotesPlaceholder')}
                value={form.description ?? ''}
                onChange={set('description')}
                error={errors.description}
            />
        </div>
    );
}

export function detailsPayload(form) {
    return {
        title: form.title,
        description: form.description || null,
        delivery_region: form.delivery_region || null,
        delivery_address: form.delivery_address || null,
        needed_by: form.needed_by || null,
    };
}
