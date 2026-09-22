import { Head, Link, useForm } from "@inertiajs/react";
import {
  Alert,
  Button,
  Checkbox,
  FormControlLabel,
  MenuItem,
  TextField,
} from "@mui/material";

type Campaign = { id: number; name: string; abbreviation: string };
type Material = {
  id: number;
  title: string;
  type: string;
  applies_to_all_campaigns: boolean;
  campaigns: { id: number }[];
};

export default function SimpleCreate({
  campaigns,
  trainingMaterials: allTrainingMaterials,
}: {
  campaigns: Campaign[];
  trainingMaterials: Material[];
}) {
  const form = useForm({
    title: "",
    description: "",
    applies_to_all_campaigns: false,
    campaign_ids: [] as number[],
    training_source: "library",
    library_material_id: "",
    training_required: true,
    training_title: "",
    training_type: "video",
    training_file: null as File | null,
    question_count: "10",
    total_points: "100",
    passing_score: "80",
    time_limit_minutes: "10",
    maximum_attempts: "1",
  });
  const submit = () =>
    form.post("/management/assessments/simple", { forceFormData: true });
  const trainingMaterials = allTrainingMaterials.filter(
    (material) =>
      form.data.applies_to_all_campaigns ||
      material.applies_to_all_campaigns ||
      material.campaigns.some((campaign) =>
        form.data.campaign_ids.includes(campaign.id),
      ),
  );

  return (
    <>
      <Head title="Create Assessment" />
      <main className="min-h-full bg-[#f7f7fa] p-4 lg:p-6">
        <div className="mx-auto max-w-3xl space-y-5">
          <header>
            <p className="text-xs font-bold tracking-[.18em] text-[#b72822] uppercase">
              Training & Assessments
            </p>
            <h1 className="mt-1 text-2xl font-bold text-slate-900">
              Create Assessment
            </h1>
            <p className="mt-1 text-sm text-slate-600">
              Set the essentials now. You will write each question on the next
              screen.
            </p>
          </header>
          {form.hasErrors && (
            <Alert severity="error">
              Check the highlighted fields and try again.
            </Alert>
          )}
          <section className="rounded-2xl border bg-white p-5 shadow-sm">
            <h2 className="font-bold">1. Basic Information</h2>
            <div className="mt-4 grid gap-4">
              <TextField
                label="Assessment Title"
                required
                value={form.data.title}
                error={Boolean(form.errors.title)}
                helperText={form.errors.title}
                onChange={(e) => form.setData("title", e.target.value)}
              />
              <FormControlLabel
                control={
                  <Checkbox
                    checked={form.data.applies_to_all_campaigns}
                    onChange={(_, value) =>
                      form.setData({
                        ...form.data,
                        applies_to_all_campaigns: value,
                        campaign_ids: [],
                        library_material_id: "",
                      })
                    }
                  />
                }
                label="All Campaigns"
              />
              {!form.data.applies_to_all_campaigns && (
                <TextField
                  select
                  label="Campaign"
                  required
                  value={form.data.campaign_ids[0] ?? ""}
                  error={Boolean(form.errors.campaign_ids)}
                  helperText={form.errors.campaign_ids}
                  onChange={(e) =>
                    form.setData({
                      ...form.data,
                      campaign_ids: [Number(e.target.value)],
                      library_material_id: "",
                    })
                  }
                >
                  {campaigns.map((campaign) => (
                    <MenuItem key={campaign.id} value={campaign.id}>
                      {campaign.name} ({campaign.abbreviation})
                    </MenuItem>
                  ))}
                </TextField>
              )}
              <TextField
                label="Description (optional)"
                multiline
                minRows={2}
                value={form.data.description}
                onChange={(e) => form.setData("description", e.target.value)}
              />
            </div>
          </section>
          <section className="rounded-2xl border bg-white p-5 shadow-sm">
            <h2 className="font-bold">2. Training</h2>
            <div className="mt-4 grid gap-4">
              <TextField
                select
                label="Training Material"
                value={form.data.training_source}
                onChange={(e) =>
                  form.setData("training_source", e.target.value)
                }
              >
                <MenuItem value="library">
                  Select from Training Library
                </MenuItem>
                <MenuItem value="upload">Upload Training Material</MenuItem>
              </TextField>
              {form.data.training_source === "library" && (
                <TextField
                  select
                  label="Training Library item"
                  value={form.data.library_material_id}
                  error={Boolean(form.errors.library_material_id)}
                  helperText={form.errors.library_material_id}
                  onChange={(e) =>
                    form.setData("library_material_id", e.target.value)
                  }
                >
                  {trainingMaterials.map((material) => (
                    <MenuItem key={material.id} value={material.id}>
                      {material.title} · {material.type}
                    </MenuItem>
                  ))}
                </TextField>
              )}
              {form.data.training_source === "upload" && (
                <>
                  <TextField
                    label="Training title"
                    value={form.data.training_title}
                    error={Boolean(form.errors.training_title)}
                    helperText={form.errors.training_title}
                    onChange={(e) =>
                      form.setData("training_title", e.target.value)
                    }
                  />
                  <TextField
                    select
                    label="Material type"
                    value={form.data.training_type}
                    onChange={(e) =>
                      form.setData("training_type", e.target.value)
                    }
                  >
                    {["video", "audio", "image", "document"].map((type) => (
                      <MenuItem key={type} value={type}>
                        {type}
                      </MenuItem>
                    ))}
                  </TextField>
                  <input
                    type="file"
                    onChange={(e) =>
                      form.setData("training_file", e.target.files?.[0] ?? null)
                    }
                  />
                  {form.errors.training_file && (
                    <p className="text-sm text-red-700">
                      {form.errors.training_file}
                    </p>
                  )}
                </>
              )}
              <FormControlLabel
                control={
                  <Checkbox
                    checked={form.data.training_required}
                    onChange={(_, value) =>
                      form.setData("training_required", value)
                    }
                  />
                }
                label="Required before taking Assessment"
              />
            </div>
          </section>
          <section className="rounded-2xl border bg-white p-5 shadow-sm">
            <h2 className="font-bold">3. Assessment Setup</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <TextField
                type="number"
                label="Number of Questions"
                value={form.data.question_count}
                error={Boolean(form.errors.question_count)}
                helperText={form.errors.question_count}
                onChange={(e) => form.setData("question_count", e.target.value)}
              />
              <TextField
                type="number"
                label="Total Points"
                value={form.data.total_points}
                error={Boolean(form.errors.total_points)}
                helperText={form.errors.total_points}
                onChange={(e) => form.setData("total_points", e.target.value)}
              />
              <TextField
                type="number"
                label="Passing Score (%)"
                value={form.data.passing_score}
                error={Boolean(form.errors.passing_score)}
                helperText={form.errors.passing_score}
                onChange={(e) => form.setData("passing_score", e.target.value)}
              />
              <TextField
                type="number"
                label="Time Limit (minutes)"
                placeholder="Leave blank for no limit"
                value={form.data.time_limit_minutes}
                error={Boolean(form.errors.time_limit_minutes)}
                helperText={
                  form.errors.time_limit_minutes ||
                  "Leave blank for no time limit"
                }
                onChange={(e) =>
                  form.setData("time_limit_minutes", e.target.value)
                }
              />
              <TextField
                select
                label="Maximum Attempts"
                value={form.data.maximum_attempts}
                onChange={(e) =>
                  form.setData("maximum_attempts", e.target.value)
                }
              >
                {["1", "2", "3"].map((value) => (
                  <MenuItem key={value} value={value}>
                    {value}
                  </MenuItem>
                ))}
              </TextField>
            </div>
          </section>
          <div className="flex justify-end gap-2">
            <Link href="/management/assessments">
              <Button>Cancel</Button>
            </Link>
            <Button
              variant="contained"
              disabled={form.processing}
              onClick={submit}
            >
              {form.processing ? "Creating…" : "Create Assessment"}
            </Button>
          </div>
        </div>
      </main>
    </>
  );
}

SimpleCreate.layout = {
  breadcrumbs: [
    { title: "Assessments", href: "/management/assessments" },
    { title: "Create Assessment", href: "/management/assessments/create" },
  ],
};
