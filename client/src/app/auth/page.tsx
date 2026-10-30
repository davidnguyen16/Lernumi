import { HugeiconsIcon } from '@hugeicons/react';
import { StudentIcon, TimeQuarterPassIcon, ToolCaseIcon, Calendar03Icon, } from '@hugeicons/core-free-icons';
import { SignInForm } from '@/components/auth/sign-in-form';
import { SignUpForm } from '@/components/auth/sign-up-form';
import { Card } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
const HIGHLIGHTS = [
    {
        icon: TimeQuarterPassIcon,
        title: 'Replay the work',
        description: 'Every action is recorded in order, and plays back from the start.',
    },
    {
        icon: ToolCaseIcon,
        title: 'Many tools, one place',
        description: 'Code and diagrams today, more as your subjects need them.',
    },
    {
        icon: Calendar03Icon,
        title: 'Deadlines that find you',
        description: 'Due dates, class schedules, and anything you add yourself.',
    },
] as const;
export default function AuthPage() {
    return (<div className="bg-muted/40 relative flex min-h-svh items-center justify-center overflow-hidden p-4 md:p-8">

            <div aria-hidden className="text-foreground pointer-events-none absolute inset-0 opacity-[0.07]" style={{
            backgroundImage: 'radial-gradient(currentColor 1px, transparent 1px)',
            backgroundSize: '22px 22px',
        }}/>
            <div aria-hidden className="bg-primary/25 pointer-events-none absolute -top-40 -left-40 size-128 rounded-full blur-3xl"/>
            <div aria-hidden className="bg-accent/40 pointer-events-none absolute -right-40 -bottom-40 size-128 rounded-full blur-3xl"/>


            <Card className="relative w-full max-w-4xl gap-0 p-0 shadow-xl">
                <div className="grid md:grid-cols-2">

                    <aside className="bg-primary text-primary-foreground relative hidden flex-col justify-between overflow-hidden p-8 md:flex">
                        <div aria-hidden className="pointer-events-none absolute inset-0 opacity-15" style={{
            backgroundImage: 'radial-gradient(currentColor 1px, transparent 1px)',
            backgroundSize: '16px 16px',
        }}/>
                        <div aria-hidden className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full opacity-20 blur-3xl" style={{
            background: 'radial-gradient(circle, white, transparent 70%)',
        }}/>

                        <div className="relative flex items-center gap-2.5">
                            <div className="bg-primary-foreground/15 flex size-9 items-center justify-center rounded-xl">
                                <HugeiconsIcon icon={StudentIcon} strokeWidth={2} className="size-5"/>
                            </div>
                            <span className="font-heading text-lg font-medium">
                                Lern<span className="text-brand-teal">umi</span>
                            </span>
                        </div>

                        <div className="relative flex flex-col gap-7 py-10">
                            <p className="font-heading text-2xl leading-snug font-medium">
                                Not just the finished work — how it came to be.
                            </p>

                            <ul className="flex flex-col gap-5">
                                {HIGHLIGHTS.map((item) => (<li key={item.title} className="flex gap-3">
                                        <div className="bg-primary-foreground/15 mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg">
                                            <HugeiconsIcon icon={item.icon} strokeWidth={2} className="size-4"/>
                                        </div>
                                        <div className="flex flex-col gap-0.5">
                                            <span className="text-sm font-medium">
                                                {item.title}
                                            </span>
                                            <span className="text-primary-foreground/70 text-sm">
                                                {item.description}
                                            </span>
                                        </div>
                                    </li>))}
                            </ul>
                        </div>

                        <p className="text-primary-foreground/60 relative text-xs">
                            A learning platform for any institution
                        </p>
                    </aside>


                    <div className="flex flex-col gap-7 p-8">

                        <div className="flex items-center gap-2.5 md:hidden">
                            <div className="bg-primary text-primary-foreground flex size-9 items-center justify-center rounded-xl">
                                <HugeiconsIcon icon={StudentIcon} strokeWidth={2} className="size-5"/>
                            </div>
                            <span className="font-heading text-lg font-medium">
                                Lern<span className="text-brand-teal">umi</span>
                            </span>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <h1 className="font-heading text-2xl font-medium tracking-tight">
                                Get started
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                Sign in to continue, or create a new account.
                            </p>
                        </div>

                        <Tabs defaultValue="sign-in">
                            <TabsList className="w-full">
                                <TabsTrigger value="sign-in" className="flex-1">
                                    Sign in
                                </TabsTrigger>
                                <TabsTrigger value="sign-up" className="flex-1">
                                    Sign up
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="sign-in" className="pt-6">
                                <SignInForm />
                            </TabsContent>

                            <TabsContent value="sign-up" className="pt-6">
                                <SignUpForm />
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </Card>
        </div>);
}
