import { SelectField, TextAreaField, TextField } from '../../components/ui';
import { TANZANIA_REGIONS } from '../../lib/format';

/** Title, notes and delivery fields shared by create and edit. */
export default function RequestDetailsFields({ form, setForm, errors = {} }) {
    const set = (field) => (event) => setForm({ ...form, [field]: event.target.value });
    const today = new Date().toISOString().slice(0, 10);

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <TextField
                className="sm:col-span-2"
                label="Request title"
                hint="Name the job so suppliers know what they are quoting for."
                placeholder="Ground floor slab, Kibamba"
                required
                maxLength={255}
                value={form.title}
                onChange={set('title')}
                error={errors.title}
            />
            <SelectField label="Delivery region" optional value={form.delivery_region ?? ''} onChange={set('delivery_region')} error={errors.delivery_region}>
                <option value="">Choose a region</option>
                {TANZANIA_REGIONS.map((region) => (
                    <option key={region}>{region}</option>
                ))}
            </SelectField>
            <TextField
                label="Needed on site by"
                optional
                type="date"
                min={today}
                value={form.needed_by ?? ''}
                onChange={set('needed_by')}
                error={errors.needed_by}
            />
            <TextField
                className="sm:col-span-2"
                label="Delivery address"
                optional
                placeholder="Street, area or landmark"
                maxLength={500}
                value={form.delivery_address ?? ''}
                onChange={set('delivery_address')}
                error={errors.delivery_address}
            />
            <TextAreaField
                className="sm:col-span-2"
                label="Notes for suppliers"
                optional
                placeholder="Access to site, offloading, payment terms you prefer"
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
