import { BookOpen01Icon, ChartUpIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import { Reveal } from '@/components/reveal';
import { cn } from '@/lib/utils';

const SDGS = [
    {
        icon: BookOpen01Icon,
        code: 'SDG 4',
        label: 'Quality Education',
        ink: 'text-primary',
        surface: 'bg-brand-teal/10',
    },
    {
        icon: ChartUpIcon,
        code: 'SDG 8',
        label: 'Decent Work and Economic Growth',
        ink: 'text-brand-amber',
        surface: 'bg-brand-amber/10',
    },
] as const;

export function MissionStory() {
    return (
        <section className="py-20 md:py-28">
            <div className="mx-auto grid max-w-295 items-center gap-12 px-5 md:px-8 lg:grid-cols-2">
                <Reveal variant="left">
                    <div className="space-y-10">
                        <div>
                            <p className="text-brand-teal mb-3 text-3xl font-semibold">
                                Our Mission
                            </p>
                            <h2 className="font-heading mb-4 text-[28px] font-bold tracking-tight">
                                Better Evidence for Better Learning.
                            </h2>
                            <p className="text-muted-foreground leading-relaxed">
                                Our mission is to give students the freedom to add and combine the
                                tools that suit each subject, build practical skills and show the
                                real work behind every outcome. Educators gain a more complete and
                                equitable view of both progress and results.
                            </p>
                        </div>

                        <div>
                            <p className="text-brand-violet mb-3 text-3xl font-semibold">
                                Our Story
                            </p>
                            <h2 className="font-heading mb-4 text-[28px] font-bold tracking-tight">
                                Born from a Simple Question.
                            </h2>
                            <p className="text-muted-foreground leading-relaxed">
                                Lernumi began with the belief that a final submission rarely tells
                                the whole story and that one fixed workspace cannot fit every
                                subject. We created a flexible platform where students can bring
                                together multiple tools while edits, decisions and problem-solving
                                remain part of the learning record.
                            </p>
                        </div>
                    </div>
                </Reveal>

                <Reveal variant="right">
                    <div className="mx-auto grid max-w-130 gap-20 pb-16 sm:grid-cols-2 sm:gap-5">
                        {SDGS.map((sdg) => (
                            <div key={sdg.code} className="relative">
                                <div
                                    className={cn(
                                        'border-border flex aspect-square items-center justify-center rounded-3xl border',
                                        sdg.surface,
                                    )}
                                >
                                    <HugeiconsIcon
                                        icon={sdg.icon}
                                        strokeWidth={1.5}
                                        className={cn('size-20', sdg.ink)}
                                        aria-hidden
                                    />
                                </div>
                                <div className="border-border bg-card absolute right-0 -bottom-16 flex h-24 w-2/3 flex-col items-center justify-center rounded-xl border p-3 text-center shadow-xl sm:w-3/4">
                                    <p className={`text-xl font-bold ${sdg.ink}`}>{sdg.code}</p>
                                    <p className="text-muted-foreground mt-1 text-sm">
                                        {sdg.label}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Reveal>
            </div>
        </section>
    );
}
