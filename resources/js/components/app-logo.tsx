import { usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

/**
 * Same mark as the public home header — keeps admin and landing visually aligned.
 */
export default function AppLogo() {
    const { name } = usePage<{ name: string }>().props;

    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-xl bg-zinc-900 text-white shadow-sm dark:bg-zinc-100 dark:text-zinc-900">
                <ShieldCheck className="size-4" strokeWidth={2} aria-hidden />
            </div>
            <div className="ml-1 grid min-w-0 flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}
