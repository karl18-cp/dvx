import { Autocomplete, Checkbox, TextField } from '@mui/material';

export type CampaignOption = {
    id: number;
    name: string;
    abbreviation?: string;
};

export function CampaignScopeField({
    campaigns,
    all,
    selected,
    onChange,
}: {
    campaigns: CampaignOption[];
    all: boolean;
    selected: number[];
    onChange: (all: boolean, ids: number[]) => void;
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <label className="flex items-center gap-2 font-semibold">
                <Checkbox
                    size="small"
                    checked={all}
                    onChange={(e) => onChange(e.target.checked, [])}
                />
                All Campaigns
            </label>
            {!all && (
                <Autocomplete
                    multiple
                    disableCloseOnSelect
                    options={campaigns}
                    value={campaigns.filter((c) => selected.includes(c.id))}
                    getOptionLabel={(c) => c.name}
                    isOptionEqualToValue={(a, b) => a.id === b.id}
                    onChange={(_, values) =>
                        onChange(
                            false,
                            values.map((c) => c.id),
                        )
                    }
                    renderOption={(props, campaign, state) => {
                        const { key, ...rest } = props;

                        return (
                            <li key={key} {...rest}>
                                <Checkbox
                                    size="small"
                                    checked={state.selected}
                                />
                                {campaign.name} ({campaign.abbreviation})
                            </li>
                        );
                    }}
                    renderInput={(params) => (
                        <TextField
                            {...params}
                            size="small"
                            label="Specific Campaigns"
                        />
                    )}
                />
            )}
            <p className="mt-1 text-slate-500">
                {all
                    ? 'Available to current and future Campaigns.'
                    : 'Available only to selected Campaigns.'}
            </p>
        </div>
    );
}

export function CampaignScopeBadge({
    all,
    campaigns = [],
}: {
    all: boolean;
    campaigns?: CampaignOption[];
}) {
    return (
        <span
            className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-700"
            title={campaigns.map((c) => c.name).join(', ')}
        >
            {all
                ? 'All Campaigns'
                : campaigns.map((c) => c.abbreviation || c.name).join(' + ') ||
                  'No Campaign'}
        </span>
    );
}
