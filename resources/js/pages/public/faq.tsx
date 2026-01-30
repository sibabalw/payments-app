import { PublicCard } from '@/components/public-card';
import { PublicCtaBand } from '@/components/public-cta-band';
import { PublicInnerHero } from '@/components/public-inner-hero';
import { AnimatedSection } from '@/components/public-motion';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { register } from '@/routes';
import { PublicFooter } from '@/components/public-footer';
import { PublicNav } from '@/components/public-nav';

const faqItems = [
    {
        question: 'What is SwiftPay?',
        answer:
            'SwiftPay is a payment and payroll automation platform for South African businesses. Your funds stay secure in bank-controlled escrow while we handle full tax compliance (PAYE, UIF, SDL), time tracking, and employee self-service—so you can focus on running your business.',
    },
    {
        question: 'Who is it for?',
        answer:
            'South African businesses—from startups to enterprises—that want to automate payments and payroll without spreadsheets or manual tax work. Whether you run payments only, payroll only, or both, SwiftPay is built for you.',
    },
    {
        question: 'What to expect?',
        answer:
            'Less manual work, fewer errors, a full audit trail, and bank-controlled escrow so your funds stay secure. You get one platform for payment scheduling, payroll with SA tax (PAYE, UIF, SDL), compliance (UI-19, EMP201, IRP5), time tracking, and employee self-service.',
    },
    {
        question: 'How does escrow work?',
        answer:
            'You deposit funds into a bank-controlled escrow account. SwiftPay never stores, touches, or holds your money in the app. When payments or payroll run, the bank releases funds. We charge a transparent fee per deposit (1.5%), not per transaction. If we fail to deliver, the bank returns money to you.',
    },
    {
        question: 'What about tax compliance?',
        answer:
            'SwiftPay handles full South African tax compliance: automatic PAYE, UIF, and SDL calculations; UI-19 declarations; EMP201 submissions; and IRP5 certificates. SARS-ready exports and complete compliance tracking are included—no manual paperwork.',
    },
    {
        question: 'How does pricing work?',
        answer:
            'SwiftPay uses a simple, deposit-based escrow model. There are no per-transaction fees or hidden costs. A 1.5% fee applies per deposit (not per transaction). Your deposit authorizes usage up to the deposited amount minus the fee. We never hold your money—transparent fees, complete control.',
    },
    {
        question: 'How do I get started?',
        answer:
            'Sign up for a free trial (no credit card required), add your business and bank details, set up your escrow, then add recipients or employees and define your payment or payroll schedules. Fund escrow and let SwiftPay run. You can also explore our How it works page for a step-by-step guide.',
    },
];

export default function Faq() {
    const [openIndex, setOpenIndex] = useState<number | null>(0);
    const reducedMotion = useReducedMotion();
    const duration = reducedMotion ? 0 : 0.25;

    return (
        <>
            <Head title="FAQ - SwiftPay" />
            <div className="flex min-h-screen flex-col">
                <PublicNav />

                <PublicInnerHero
                    title="Frequently Asked Questions"
                    description="Quick answers about SwiftPay: what it is, who it's for, escrow, tax compliance, and pricing."
                />

                {/* FAQ list */}
                <AnimatedSection className="py-16">
                    <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                        <div className="space-y-5">
                            {faqItems.map((item, index) => {
                                const isOpen = openIndex === index;
                                return (
                                    <PublicCard
                                        key={index}
                                        variant="glass"
                                        className="overflow-hidden p-0"
                                    >
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setOpenIndex(isOpen ? null : index)
                                            }
                                            className="flex w-full items-center justify-between gap-4 px-6 py-5 text-left font-semibold text-foreground transition-colors hover:bg-muted/50 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            aria-expanded={isOpen}
                                            aria-controls={`faq-answer-${index}`}
                                            id={`faq-question-${index}`}
                                        >
                                            {item.question}
                                            <ChevronDown
                                                className={`h-5 w-5 shrink-0 transition-transform duration-200 ${isOpen ? 'rotate-180' : ''}`}
                                            />
                                        </button>
                                        <motion.div
                                            id={`faq-answer-${index}`}
                                            role="region"
                                            aria-labelledby={`faq-question-${index}`}
                                            initial={false}
                                            animate={{
                                                height: isOpen ? 'auto' : 0,
                                                opacity: isOpen ? 1 : 0,
                                            }}
                                            transition={{
                                                duration,
                                                ease: [0.25, 0.46, 0.45, 0.94],
                                            }}
                                            className="overflow-hidden"
                                        >
                                            <p className="border-t border-border/60 px-6 py-4 text-muted-foreground">
                                                {item.answer}
                                            </p>
                                        </motion.div>
                                    </PublicCard>
                                );
                            })}
                        </div>
                        <div className="mt-12 text-center">
                            <p className="text-muted-foreground">Still have questions?</p>
                            <Link href="/contact" className="mt-2 inline-block">
                                <Button variant="outline">Contact us</Button>
                            </Link>
                        </div>
                    </div>
                </AnimatedSection>

                <PublicCtaBand
                    title="Ready to get started?"
                    description="No credit card required. Start your free trial today."
                >
                    <Link href={register()}>
                        <Button variant="gradient" size="lg">
                            Start Free Trial
                        </Button>
                    </Link>
                </PublicCtaBand>

                <PublicFooter />
            </div>
        </>
    );
}
