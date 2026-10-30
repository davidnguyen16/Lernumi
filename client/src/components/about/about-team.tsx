import { UserGroupIcon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import { Reveal } from '@/components/reveal';
import { Empty, EmptyHeader, EmptyMedia, EmptyTitle } from '@/components/ui/empty';

export function AboutTeam() {
    return (
        <section id="team" className="scroll-mt-20 py-20 md:py-28">
            <div className="mx-auto max-w-225 px-5 md:px-8">
                <Reveal variant="blur">
                    <div className="mb-12 text-center">
                        <p className="text-brand-teal mb-3 text-3xl font-semibold">Meet Our Team</p>
                        <h2 className="font-heading mb-4 text-3xl font-bold tracking-tight md:text-4xl">
                            The People Behind Lernumi
                        </h2>
                    </div>
                </Reveal>

                <Reveal variant="up">
                    <Empty className="border-border bg-card rounded-2xl border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <HugeiconsIcon icon={UserGroupIcon} strokeWidth={2} aria-hidden />
                            </EmptyMedia>
                            <EmptyTitle>No team members to display.</EmptyTitle>
                        </EmptyHeader>
                    </Empty>
                </Reveal>
            </div>
        </section>
    );
}
