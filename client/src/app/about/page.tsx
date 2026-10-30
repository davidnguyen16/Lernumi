import type { Metadata } from 'next';
import { AboutCta } from '@/components/about/about-cta';
import { AboutHeader } from '@/components/about/about-header';
import { AboutHero } from '@/components/about/about-hero';
import { AboutTeam } from '@/components/about/about-team';
import { AboutValues } from '@/components/about/about-values';
import { MissionStory } from '@/components/about/mission-story';
import { SiteFooter } from '@/components/home/site-footer';
export const metadata: Metadata = {
    title: 'About — Lernumi',
    description: 'Why Lernumi exists: making student thinking visible, and giving educators fairer evidence of how learning happens.',
};
export default function AboutPage() {
    return (<div className="bg-background text-foreground min-h-svh overflow-x-hidden">
            <AboutHeader />
            <main>
                <AboutHero />
                <MissionStory />
                <AboutValues />
                <AboutTeam />
                <AboutCta />
            </main>
            <SiteFooter />
        </div>);
}
