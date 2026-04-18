import type { FormDataErrors } from '@inertiajs/core';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect } from 'react';


import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    edit as adminLandingEdit,
    reset as resetLandingRoute,
    update,
} from '@/routes/admin/landing';

/** Subset of landing copy editable here; other strings stay in config/landing.php. */
export type LandingForm = {
    meta: { document_title: string };
    header: { brand: string; tagline: string };
    hero: {
        badge: string;
        headline: string;
        intro_before_provider: string;
        intro_after_provider: string;
        intro_driver_env: string;
        intro_after_code: string;
    };
    footer: { text: string };
};

type PageProps = {
    landing: LandingForm;
    has_custom_content: boolean;
};

/** Matches `useForm({ landing })` — required for `FormDataErrors<T>`. */
type LandingFormData = { landing: LandingForm };

/** Laravel validation keys are dotted strings; widen for lookup. */
type ErrorBag = Record<string, string | string[] | undefined>;

function fieldError(
    errors: FormDataErrors<LandingFormData>,
    path: string,
): string | undefined {
    const v = (errors as ErrorBag)[path];

    if (v === undefined) {
        return undefined;
    }

    return Array.isArray(v) ? v[0] : v;
}

function firstFormErrorMessage(
    errors: FormDataErrors<LandingFormData>,
): string | null {
    const bag = errors as ErrorBag;
    const keys = Object.keys(bag);

    if (keys.length === 0) {
        return null;
    }

    const v = bag[keys[0]];

    if (v === undefined) {
        return null;
    }

    return Array.isArray(v) ? v[0] : v;
}

function TextField({
    id,
    label,
    hint,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    hint?: string;
    value: string;
    onChange: (v: string) => void;
    error?: string;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            {hint && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <Input
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-invalid={!!error}
                className={error ? 'border-destructive' : undefined}
            />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

function TextAreaField({
    id,
    label,
    hint,
    value,
    onChange,
    rows = 3,
    error,
}: {
    id: string;
    label: string;
    hint?: string;
    value: string;
    onChange: (v: string) => void;
    rows?: number;
    error?: string;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            {hint && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <textarea
                id={id}
                rows={rows}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                spellCheck
                className={cn(
                    'border-input placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                    error && 'border-destructive',
                )}
                aria-invalid={!!error}
            />
            {error && <p className="text-sm text-destructive">{error}</p>}
        </div>
    );
}

export default function AdminLanding({
    landing: landingProp,
    has_custom_content,
}: PageProps) {
    const { status } = usePage().props;
    const { data, setData, put, processing, errors, reset: resetForm } =
        useForm({
            landing: landingProp,
        });

    // Only sync from server after a successful save/reset. Syncing on every
    // `landingProp` reference change resets the form and clears edits before submit.
    useEffect(() => {
        if (status === 'landing-saved' || status === 'landing-reset') {
            setData('landing', landingProp);
        }
    }, [status, landingProp, setData]);

    const e = errors;
    const L = data.landing;

    const onSubmit = useCallback(
        (ev: React.FormEvent) => {
            ev.preventDefault();
            put(update.url(), { preserveScroll: true });
        },
        [put],
    );

    const onReset = useCallback(() => {
        if (
            !window.confirm(
                'Reset saved landing copy to defaults from config/landing.php? Database overrides for this page will be removed.',
            )
        ) {
            return;
        }

        router.post(resetLandingRoute.url(), {}, { preserveScroll: true });
    }, []);

    return (
        <>
            <Head title="Landing page copy" />
            <div className="mx-auto flex max-w-2xl flex-col gap-8 p-4 pb-16">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Landing page copy
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Edit the main headline, hero text, and footer on the
                        home page. Input labels, summary panel, toasts, and API
                        key notices are still defined in{' '}
                        <code className="rounded bg-muted px-1 py-0.5 text-xs">
                            config/landing.php
                        </code>
                        .
                    </p>
                </div>

                {status === 'landing-saved' && (
                    <Alert>
                        <AlertTitle>Saved</AlertTitle>
                        <AlertDescription>
                            Open the home page to review your changes.
                        </AlertDescription>
                    </Alert>
                )}
                {status === 'landing-reset' && (
                    <Alert>
                        <AlertTitle>Reset</AlertTitle>
                        <AlertDescription>
                            Saved overrides were cleared. Defaults from config
                            apply until you save again.
                        </AlertDescription>
                    </Alert>
                )}

                {Object.keys(errors).length > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>Could not save</AlertTitle>
                        <AlertDescription>
                            {firstFormErrorMessage(errors)}
                            {Object.keys(errors).length > 1 && (
                                <span className="mt-1 block text-xs opacity-90">
                                    Fix the fields below and try again.
                                </span>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={onSubmit} className="flex flex-col gap-8">
                    <Card>
                        <CardHeader>
                            <CardTitle>Branding</CardTitle>
                            <CardDescription>
                                Browser tab title and the top bar on the public
                                home page.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <TextField
                                id="meta_title"
                                label="Browser tab title"
                                value={L.meta.document_title}
                                onChange={(v) =>
                                    setData('landing.meta.document_title', v)
                                }
                                error={fieldError(
                                    e,
                                    'landing.meta.document_title',
                                )}
                            />
                            <TextField
                                id="header_brand"
                                label="Site name (header)"
                                value={L.header.brand}
                                onChange={(v) =>
                                    setData('landing.header.brand', v)
                                }
                                error={fieldError(e, 'landing.header.brand')}
                            />
                            <TextAreaField
                                id="header_tagline"
                                label="Tagline under the site name"
                                value={L.header.tagline}
                                onChange={(v) =>
                                    setData('landing.header.tagline', v)
                                }
                                rows={2}
                                error={fieldError(e, 'landing.header.tagline')}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Hero</CardTitle>
                            <CardDescription>
                                The headline and paragraph under the badge — the
                                same block as “Check a story before you share it”
                                and the text about Gemini /{' '}
                                <code className="text-xs">NEWS_ANALYZER_DRIVER</code>.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <TextField
                                id="hero_badge"
                                label="Badge text (next to the provider chip)"
                                value={L.hero.badge}
                                onChange={(v) =>
                                    setData('landing.hero.badge', v)
                                }
                                error={fieldError(e, 'landing.hero.badge')}
                            />
                            <TextField
                                id="hero_headline"
                                label="Headline"
                                hint='e.g. “Check a story before you share it”.'
                                value={L.hero.headline}
                                onChange={(v) =>
                                    setData('landing.hero.headline', v)
                                }
                                error={fieldError(e, 'landing.hero.headline')}
                            />
                            <TextAreaField
                                id="hero_intro_before"
                                label="Paragraph — start (before the bold provider name)"
                                hint="Sentence that leads into the provider name (e.g. Google Gemini or Groq)."
                                value={L.hero.intro_before_provider}
                                onChange={(v) =>
                                    setData(
                                        'landing.hero.intro_before_provider',
                                        v,
                                    )
                                }
                                rows={3}
                                error={fieldError(
                                    e,
                                    'landing.hero.intro_before_provider',
                                )}
                            />
                            <TextAreaField
                                id="hero_intro_after"
                                label="Paragraph — middle (after provider name, before env var)"
                                hint="Optional. Often starts with a period and “Switch backends with”."
                                value={L.hero.intro_after_provider}
                                onChange={(v) =>
                                    setData(
                                        'landing.hero.intro_after_provider',
                                        v,
                                    )
                                }
                                rows={2}
                                error={fieldError(
                                    e,
                                    'landing.hero.intro_after_provider',
                                )}
                            />
                            <TextField
                                id="hero_env"
                                label="Env var label (shown in monospace)"
                                hint="Optional. Shown as a code chip, e.g. NEWS_ANALYZER_DRIVER."
                                value={L.hero.intro_driver_env}
                                onChange={(v) =>
                                    setData('landing.hero.intro_driver_env', v)
                                }
                                error={fieldError(
                                    e,
                                    'landing.hero.intro_driver_env',
                                )}
                            />
                            <TextAreaField
                                id="hero_intro_after_code"
                                label="Paragraph — end (after the env var)"
                                hint="Optional. Closing sentence of the hero paragraph."
                                value={L.hero.intro_after_code}
                                onChange={(v) =>
                                    setData('landing.hero.intro_after_code', v)
                                }
                                rows={3}
                                error={fieldError(
                                    e,
                                    'landing.hero.intro_after_code',
                                )}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Footer</CardTitle>
                            <CardDescription>
                                Small disclaimer at the bottom of the home page.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TextAreaField
                                id="foot"
                                label="Footer text"
                                hint='e.g. “Educational demo — not legal or medical advice…”'
                                value={L.footer.text}
                                onChange={(v) =>
                                    setData('landing.footer.text', v)
                                }
                                rows={4}
                                error={fieldError(e, 'landing.footer.text')}
                            />
                        </CardContent>
                    </Card>

                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving…' : 'Save'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => resetForm()}
                            disabled={processing}
                        >
                            Discard changes
                        </Button>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={onReset}
                            disabled={processing || !has_custom_content}
                        >
                            Reset to config defaults
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

AdminLanding.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Landing page',
            href: adminLandingEdit.url(),
        },
    ],
};
