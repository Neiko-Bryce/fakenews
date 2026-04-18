import { Head, Link, usePage } from '@inertiajs/react';
import { KeyRound, ShieldCheck, Sparkles, User } from 'lucide-react';

import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { edit as adminLandingEdit } from '@/routes/admin/landing';
import { edit as profileEdit } from '@/routes/profile';
import { edit as securityEdit } from '@/routes/security';

type PageProps = {
    analyzerDriver: string;
    analyzerConfigured: boolean;
};

export default function Dashboard({
    analyzerDriver,
    analyzerConfigured,
}: PageProps) {
    const { auth } = usePage().props;
    const user = auth.user;
    const driverLabel = analyzerDriver === 'groq' ? 'Groq' : 'Gemini';

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-8 overflow-x-auto p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {user?.name ? `Hi, ${user.name}` : 'Dashboard'}
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground">
                        Account settings and AI backend status. Story checks run
                        on the public home page ({' '}
                        <span className="font-medium text-foreground">/</span>
                        ).
                        {auth.user?.is_admin &&
                            ' Admins can edit landing copy below.'}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card className="border-zinc-200/80 dark:border-zinc-800">
                        <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                                <ShieldCheck
                                    className="size-5 text-zinc-700 dark:text-zinc-200"
                                    strokeWidth={2}
                                    aria-hidden
                                />
                            </div>
                            <div className="min-w-0 space-y-1">
                                <CardTitle className="text-lg">
                                    AI backend
                                </CardTitle>
                                <CardDescription className="space-y-1">
                                    <span className="block">
                                        Driver:{' '}
                                        <span className="font-medium text-foreground">
                                            {driverLabel}
                                        </span>
                                    </span>
                                    <span
                                        className={
                                            analyzerConfigured
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-amber-600 dark:text-amber-400'
                                        }
                                    >
                                        {analyzerConfigured
                                            ? 'API key configured — ready to analyze.'
                                            : 'API key missing — analysis will show an error on the home page until configured.'}
                                    </span>
                                </CardDescription>
                            </div>
                        </CardHeader>
                    </Card>

                    <div className="flex flex-col gap-4">
                        <Link
                            href={profileEdit()}
                            className="block rounded-xl outline-none ring-offset-background transition hover:opacity-95 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <Card className="border-zinc-200/80 transition hover:border-zinc-300/90 dark:border-zinc-800 dark:hover:border-zinc-700">
                                <CardHeader className="flex flex-row items-center gap-3 space-y-0 py-4">
                                    <User
                                        className="size-5 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <div>
                                        <CardTitle className="text-base">
                                            Profile
                                        </CardTitle>
                                        <CardDescription>
                                            Name, email, appearance
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                            </Card>
                        </Link>
                        <Link
                            href={securityEdit()}
                            className="block rounded-xl outline-none ring-offset-background transition hover:opacity-95 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <Card className="border-zinc-200/80 transition hover:border-zinc-300/90 dark:border-zinc-800 dark:hover:border-zinc-700">
                                <CardHeader className="flex flex-row items-center gap-3 space-y-0 py-4">
                                    <KeyRound
                                        className="size-5 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <div>
                                        <CardTitle className="text-base">
                                            Security
                                        </CardTitle>
                                        <CardDescription>
                                            Password and two-factor
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                            </Card>
                        </Link>
                    </div>
                </div>

                {auth.user?.is_admin && (
                    <div>
                        <h2 className="mb-3 text-sm font-medium text-muted-foreground">
                            Administration
                        </h2>
                        <Link
                            href={adminLandingEdit.url()}
                            className="block max-w-xl rounded-xl outline-none ring-offset-background transition hover:opacity-95 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <Card className="border-primary/20 bg-primary/5 dark:bg-primary/10">
                                <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                                    <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                                        <Sparkles
                                            className="size-5"
                                            aria-hidden
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <CardTitle className="text-lg">
                                            Landing page copy
                                        </CardTitle>
                                        <CardDescription>
                                            Headline, hero paragraph, branding,
                                            and footer on the public home page.
                                        </CardDescription>
                                    </div>
                                </CardHeader>
                            </Card>
                        </Link>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
