import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Mail, MessageSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login, register } from '@/routes';
import { useForm } from '@inertiajs/react';

export default function Contact() {
    const { data, setData, post, processing } = useForm({
        name: '',
        email: '',
        message: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // In a real app, this would send to a contact form handler
        alert('Thank you for your message! We will get back to you soon.');
    };

    return (
        <>
            <Head title="Contact - Swift Pay" />
            <div className="flex min-h-screen flex-col">
                <nav className="border-b bg-white dark:bg-gray-900">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 items-center justify-between">
                            <Link href="/" className="text-2xl font-bold text-primary">Swift Pay</Link>
                            <div className="flex items-center gap-4">
                                <Link href={login()}>
                                    <Button variant="ghost" size="sm">Log in</Button>
                                </Link>
                                <Link href={register()}>
                                    <Button size="sm">Get Started</Button>
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>

                <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                    <Link href="/" className="mb-8 inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to home
                    </Link>

                    <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Get in Touch
                    </h1>
                    <p className="mt-4 text-lg text-gray-600 dark:text-gray-300">
                        Have questions? We'd love to hear from you.
                    </p>

                    <div className="mt-12 grid gap-8 lg:grid-cols-2">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="space-y-6">
                                    <div className="flex items-start gap-4">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                            <Mail className="h-6 w-6 text-primary" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold">Email Us</h3>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                support@swiftpay.com
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-4">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                            <MessageSquare className="h-6 w-6 text-primary" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold">Support Hours</h3>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                                                Monday - Friday: 9:00 AM - 6:00 PM EST
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardContent className="pt-6">
                                <form onSubmit={submit} className="space-y-4">
                                    <div>
                                        <Label htmlFor="name">Name</Label>
                                        <Input
                                            id="name"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="message">Message</Label>
                                        <textarea
                                            id="message"
                                            className="flex min-h-[120px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing} className="w-full">
                                        Send Message
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
