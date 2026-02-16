import InputError from '@/components/input-error';
import ConfirmationDialog from '@/components/confirmation-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Time Tracking', href: '/time-tracking' },
    { title: 'Manual Entry', href: '#' },
];

export default function TimeTrackingManual({ employees, businesses, selectedBusinessId, selectedDate, entries }: any) {
    const [businessId, setBusinessId] = useState(selectedBusinessId || businesses[0]?.id || '');
    const [date, setDate] = useState(selectedDate || new Date().toISOString().split('T')[0]);

    const [editingEntry, setEditingEntry] = useState<any>(null);
    const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
    const [entryToDelete, setEntryToDelete] = useState<number | null>(null);

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        employee_id: '' as string | number,
        date: date,
        sign_in_time: '',
        sign_out_time: '',
        bonus_amount: '',
        notes: '',
    });

    const handleBusinessChange = (value: string) => {
        setBusinessId(value);
        router.get('/time-tracking/manual', { business_id: value, date }, { preserveState: true });
    };

    const handleDateChange = (newDate: Date | undefined) => {
        if (newDate) {
            const dateStr = newDate.toISOString().split('T')[0];
            setDate(dateStr);
            router.get('/time-tracking/manual', { business_id: businessId, date: dateStr }, { preserveState: true });
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingEntry) {
            put(`/time-tracking/entries/${editingEntry.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    setEditingEntry(null);
                    router.reload({ only: ['entries'] });
                },
            });
        } else {
            post('/time-tracking/entries', {
                preserveScroll: true,
                onSuccess: () => {
                    reset();
                    router.reload({ only: ['entries'] });
                },
            });
        }
    };

    const handleEdit = (entry: any) => {
        setEditingEntry(entry);
        setData({
            employee_id: entry.employee_id,
            date: entry.date,
            sign_in_time: entry.sign_in_time ? new Date(entry.sign_in_time).toISOString().slice(0, 16) : '',
            sign_out_time: entry.sign_out_time ? new Date(entry.sign_out_time).toISOString().slice(0, 16) : '',
            bonus_amount: entry.bonus_amount || '',
            notes: entry.notes || '',
        });
    };

    const handleCancel = () => {
        reset();
        setEditingEntry(null);
    };

    const handleDelete = (entryId: number) => {
        setEntryToDelete(entryId);
        setDeleteConfirmOpen(true);
    };

    const confirmDelete = () => {
        if (entryToDelete) {
            destroy(`/time-tracking/entries/${String(entryToDelete)}`, {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['entries'] });
                    setDeleteConfirmOpen(false);
                    setEntryToDelete(null);
                },
            });
        }
    };

    const filteredEmployees = employees.filter((emp: any) => emp.business_id === Number(businessId));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Manual Time Entry" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Manual Time Entry</h1>
                    <Link href="/time-tracking">
                        <Button variant="outline">Back to Dashboard</Button>
                    </Link>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>{editingEntry ? 'Edit Time Entry' : 'Add Time Entry'}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                {businesses && businesses.length > 0 && (
                                    <div>
                                        <Label htmlFor="business_id">Business</Label>
                                        <Select
                                            value={String(businessId)}
                                            onValueChange={handleBusinessChange}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {businesses.map((business: any) => (
                                                    <SelectItem key={business.id} value={String(business.id)}>
                                                        {business.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                                <div>
                                    <Label htmlFor="date">Date</Label>
                                    <Input
                                        id="date"
                                        type="date"
                                        value={date}
                                        onChange={(e) => {
                                            setDate(e.target.value);
                                            router.get('/time-tracking/manual', { business_id: businessId, date: e.target.value }, { preserveState: true });
                                        }}
                                        required
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="employee_id">Employee</Label>
                                    <Select
                                        value={String(data.employee_id)}
                                        onValueChange={(value) => setData('employee_id', Number(value))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select employee" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredEmployees.map((employee: any) => (
                                                <SelectItem key={employee.id} value={String(employee.id)}>
                                                    {employee.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.employee_id} />
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="sign_in_time">Sign In Time</Label>
                                        <Input
                                            id="sign_in_time"
                                            type="datetime-local"
                                            value={data.sign_in_time}
                                            onChange={(e) => setData('sign_in_time', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.sign_in_time} />
                                    </div>

                                    <div>
                                        <Label htmlFor="sign_out_time">Sign Out Time</Label>
                                        <Input
                                            id="sign_out_time"
                                            type="datetime-local"
                                            value={data.sign_out_time}
                                            onChange={(e) => setData('sign_out_time', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.sign_out_time} />
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="bonus_amount">Bonus Amount (ZAR)</Label>
                                    <Input
                                        id="bonus_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.bonus_amount}
                                        onChange={(e) => setData('bonus_amount', e.target.value)}
                                    />
                                    <InputError message={errors.bonus_amount} />
                                </div>

                                <div>
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        rows={3}
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>
                                        {editingEntry ? 'Update Entry' : 'Create Entry'}
                                    </Button>
                                    {editingEntry && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleCancel}
                                        >
                                            Cancel
                                        </Button>
                                    )}
                                    {!editingEntry && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => reset()}
                                        >
                                            Clear
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Entries for {new Date(date).toLocaleDateString()}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {entries && entries.length > 0 ? (
                                <div className="space-y-3">
                                    {entries.map((entry: any) => (
                                        <div key={entry.id} className="p-3 border rounded-lg">
                                            <div className="flex justify-between items-start">
                                                <div>
                                                    <div className="font-medium">{entry.employee?.name}</div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {new Date(entry.sign_in_time).toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' })} - {new Date(entry.sign_out_time).toLocaleTimeString('en-ZA', { hour: '2-digit', minute: '2-digit' })}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground mt-1">
                                                        Regular: {parseFloat(entry.regular_hours).toFixed(2)}h | 
                                                        Overtime: {parseFloat(entry.overtime_hours).toFixed(2)}h |
                                                        Weekend: {parseFloat(entry.weekend_hours).toFixed(2)}h |
                                                        Holiday: {parseFloat(entry.holiday_hours).toFixed(2)}h
                                                    </div>
                                                    {entry.bonus_amount > 0 && (
                                                        <div className="text-xs text-green-600 mt-1">
                                                            Bonus: ZAR {parseFloat(entry.bonus_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2 })}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleEdit(entry)}
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() => handleDelete(entry.id)}
                                                    >
                                                        Delete
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No entries for this date.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <ConfirmationDialog
                open={deleteConfirmOpen}
                onOpenChange={setDeleteConfirmOpen}
                onConfirm={confirmDelete}
                title="Are you sure you want to delete this time entry?"
                description="This action cannot be undone. The time entry will be permanently deleted."
                confirmText="Delete"
                variant="destructive"
                processing={processing}
            />
        </AppLayout>
    );
}
