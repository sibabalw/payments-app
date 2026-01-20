import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { 
    ArrowLeft, 
    Eye, 
    Save, 
    RotateCcw, 
    GripVertical,
    Type,
    Square,
    Minus,
    Table,
    PanelTop,
    PanelBottom,
    Trash2,
    Plus,
    ChevronUp,
    ChevronDown,
    Undo2,
    Redo2
} from 'lucide-react';
import { useState, useCallback, useEffect, useRef } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Templates', href: '/templates' },
    { title: 'Editor', href: '#' },
];

interface BlockProperties {
    [key: string]: string | number | boolean | Array<{ label: string; value: string }>;
}

interface Block {
    id: string;
    type: string;
    properties: BlockProperties;
}

interface Content {
    blocks: Block[];
}

interface Props {
    type: string;
    typeName: string;
    preset: string;
    content: Content;
    presets: Record<string, string>;
    isCustomized: boolean;
    business: {
        id: number;
        name: string;
        logo: string | null;
    };
}

const blockTypes = [
    { type: 'header', label: 'Header', icon: PanelTop, description: 'Logo and business name' },
    { type: 'text', label: 'Text', icon: Type, description: 'Paragraph or heading' },
    { type: 'button', label: 'Button', icon: Square, description: 'Call-to-action button' },
    { type: 'divider', label: 'Divider', icon: Minus, description: 'Horizontal line' },
    { type: 'table', label: 'Table', icon: Table, description: 'Key-value data table' },
    { type: 'footer', label: 'Footer', icon: PanelBottom, description: 'Footer text' },
];

const generateId = () => `block-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

const getDefaultBlockProperties = (type: string): BlockProperties => {
    switch (type) {
        case 'header':
            return {
                logoUrl: '{{business_logo}}',
                businessName: '{{business_name}}',
                backgroundColor: '#1a1a1a',
                textColor: '#ffffff',
            };
        case 'text':
            return {
                content: '<p>Enter your text here...</p>',
                fontSize: '16px',
                color: '#4a4a4a',
                alignment: 'left',
            };
        case 'button':
            return {
                label: 'Click Here',
                url: '#',
                backgroundColor: '#2563eb',
                textColor: '#ffffff',
            };
        case 'divider':
            return {
                height: '1px',
                color: '#e5e5e5',
            };
        case 'table':
            return {
                rows: [
                    { label: 'Label', value: 'Value' },
                ],
                headerStyle: 'default',
                backgroundColor: '#f5f5f5',
                borderColor: '#e5e5e5',
            };
        case 'footer':
            return {
                text: '© {{year}} {{business_name}}. All rights reserved.',
                color: '#6b7280',
            };
        default:
            return {};
    }
};

// History hook for undo/redo
function useHistory<T>(initialState: T) {
    const [history, setHistory] = useState<T[]>([initialState]);
    const [currentIndex, setCurrentIndex] = useState(0);
    const isUndoRedoAction = useRef(false);

    const current = history[currentIndex];

    const set = useCallback((newState: T | ((prev: T) => T)) => {
        if (isUndoRedoAction.current) {
            isUndoRedoAction.current = false;
            return;
        }

        setHistory(prev => {
            const resolvedState = typeof newState === 'function' 
                ? (newState as (prev: T) => T)(prev[currentIndex])
                : newState;
            
            // Remove any future history if we're not at the end
            const newHistory = prev.slice(0, currentIndex + 1);
            // Limit history to 50 entries to prevent memory issues
            const limitedHistory = newHistory.length >= 50 
                ? newHistory.slice(-49) 
                : newHistory;
            return [...limitedHistory, resolvedState];
        });
        setCurrentIndex(prev => Math.min(prev + 1, 49));
    }, [currentIndex]);

    const undo = useCallback(() => {
        if (currentIndex > 0) {
            isUndoRedoAction.current = true;
            setCurrentIndex(prev => prev - 1);
        }
    }, [currentIndex]);

    const redo = useCallback(() => {
        if (currentIndex < history.length - 1) {
            isUndoRedoAction.current = true;
            setCurrentIndex(prev => prev + 1);
        }
    }, [currentIndex, history.length]);

    const canUndo = currentIndex > 0;
    const canRedo = currentIndex < history.length - 1;

    // Reset history when initialState changes (e.g., preset change)
    const reset = useCallback((newState: T) => {
        setHistory([newState]);
        setCurrentIndex(0);
    }, []);

    return { current, set, undo, redo, canUndo, canRedo, reset };
}

export default function TemplateEditor({ type, typeName, preset: initialPreset, content: initialContent, presets, isCustomized, business }: Props) {
    const { 
        current: blocks, 
        set: setBlocks, 
        undo, 
        redo, 
        canUndo, 
        canRedo,
        reset: resetHistory 
    } = useHistory<Block[]>(initialContent.blocks || []);
    const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null);
    const [currentPreset, setCurrentPreset] = useState(initialPreset);
    const [isSaving, setIsSaving] = useState(false);
    const [isResetting, setIsResetting] = useState(false);
    const [draggedBlockType, setDraggedBlockType] = useState<string | null>(null);
    const [dragOverIndex, setDragOverIndex] = useState<number | null>(null);
    const { props } = usePage();
    const flash = props.flash as { success?: string; error?: string } | undefined;

    const selectedBlock = blocks.find(b => b.id === selectedBlockId);

    // Keyboard shortcuts for undo/redo
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'z') {
                e.preventDefault();
                if (e.shiftKey) {
                    redo();
                } else {
                    undo();
                }
            }
            if ((e.metaKey || e.ctrlKey) && e.key === 'y') {
                e.preventDefault();
                redo();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [undo, redo]);

    const handleDragStart = (e: React.DragEvent, blockType: string) => {
        setDraggedBlockType(blockType);
        e.dataTransfer.effectAllowed = 'copy';
    };

    const handleDragOver = (e: React.DragEvent, index: number) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        setDragOverIndex(index);
    };

    const handleDragLeave = () => {
        setDragOverIndex(null);
    };

    const handleDrop = (e: React.DragEvent, index: number) => {
        e.preventDefault();
        if (draggedBlockType) {
            const newBlock: Block = {
                id: generateId(),
                type: draggedBlockType,
                properties: getDefaultBlockProperties(draggedBlockType),
            };
            const newBlocks = [...blocks];
            newBlocks.splice(index, 0, newBlock);
            setBlocks(newBlocks);
            setSelectedBlockId(newBlock.id);
        }
        setDraggedBlockType(null);
        setDragOverIndex(null);
    };

    const handleCanvasDrop = (e: React.DragEvent) => {
        e.preventDefault();
        if (draggedBlockType) {
            const newBlock: Block = {
                id: generateId(),
                type: draggedBlockType,
                properties: getDefaultBlockProperties(draggedBlockType),
            };
            setBlocks([...blocks, newBlock]);
            setSelectedBlockId(newBlock.id);
        }
        setDraggedBlockType(null);
        setDragOverIndex(null);
    };

    const moveBlock = (index: number, direction: 'up' | 'down') => {
        const newBlocks = [...blocks];
        const newIndex = direction === 'up' ? index - 1 : index + 1;
        if (newIndex < 0 || newIndex >= blocks.length) return;
        [newBlocks[index], newBlocks[newIndex]] = [newBlocks[newIndex], newBlocks[index]];
        setBlocks(newBlocks);
    };

    const deleteBlock = (id: string) => {
        setBlocks(blocks.filter(b => b.id !== id));
        if (selectedBlockId === id) {
            setSelectedBlockId(null);
        }
    };

    const updateBlockProperty = (blockId: string, property: string, value: unknown) => {
        setBlocks(blocks.map(block => {
            if (block.id === blockId) {
                return {
                    ...block,
                    properties: {
                        ...block.properties,
                        [property]: value,
                    },
                };
            }
            return block;
        }));
    };

    const handlePresetChange = async (newPreset: string) => {
        setCurrentPreset(newPreset);
        try {
            const response = await fetch(`/templates/${type}/preset`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ preset: newPreset }),
            });
            const data = await response.json();
            if (data.content) {
                // Reset history when loading a new preset
                resetHistory(data.content.blocks || []);
                setSelectedBlockId(null);
            }
        } catch (error) {
            console.error('Failed to load preset:', error);
        }
    };

    const handleSave = () => {
        setIsSaving(true);
        router.put(`/templates/${type}`, {
            content: { blocks },
            preset: currentPreset,
        }, {
            preserveScroll: true,
            onFinish: () => setIsSaving(false),
        });
    };

    const handleReset = () => {
        if (!confirm('Are you sure you want to reset this template to default? This will remove all customizations.')) {
            return;
        }
        setIsResetting(true);
        router.post(`/templates/${type}/reset`, {}, {
            preserveScroll: true,
            onFinish: () => setIsResetting(false),
        });
    };

    const handlePreview = () => {
        window.open(`/templates/${type}/preview`, '_blank');
    };

    const renderBlockPreview = (block: Block) => {
        const props = block.properties;
        
        switch (block.type) {
            case 'header':
                return (
                    <div 
                        className="p-4 rounded-t-lg"
                        style={{ backgroundColor: props.backgroundColor as string }}
                    >
                        <span style={{ color: props.textColor as string }} className="font-semibold">
                            {props.businessName as string}
                        </span>
                    </div>
                );
            case 'text':
                return (
                    <div 
                        className="p-4"
                        style={{ 
                            color: props.color as string,
                            textAlign: props.alignment as 'left' | 'center' | 'right',
                            fontSize: props.fontSize as string,
                        }}
                        dangerouslySetInnerHTML={{ __html: props.content as string }}
                    />
                );
            case 'button':
                return (
                    <div className="p-4 text-center">
                        <span 
                            className="inline-block px-6 py-2 rounded-lg font-medium"
                            style={{ 
                                backgroundColor: props.backgroundColor as string,
                                color: props.textColor as string,
                            }}
                        >
                            {props.label as string}
                        </span>
                    </div>
                );
            case 'divider':
                return (
                    <div className="px-4 py-2">
                        <div 
                            style={{ 
                                height: props.height as string,
                                backgroundColor: props.color as string,
                            }}
                        />
                    </div>
                );
            case 'table':
                return (
                    <div className="p-4">
                        <div 
                            className="rounded border-l-4"
                            style={{ 
                                backgroundColor: props.backgroundColor as string,
                                borderColor: props.borderColor as string,
                            }}
                        >
                            {(props.rows as Array<{ label: string; value: string }>)?.map((row, i) => (
                                <div key={i} className="flex border-b last:border-b-0" style={{ borderColor: props.borderColor as string }}>
                                    <div className="p-2 font-medium w-1/3 text-gray-900">{row.label}:</div>
                                    <div className="p-2 w-2/3 text-gray-700">{row.value}</div>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            case 'footer':
                return (
                    <div 
                        className="p-4 rounded-b-lg text-center text-sm"
                        style={{ 
                            backgroundColor: '#f5f5f5',
                            color: props.color as string 
                        }}
                        dangerouslySetInnerHTML={{ __html: props.text as string }}
                    />
                );
            default:
                return <div className="p-4 text-gray-400">Unknown block type</div>;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${typeName}`} />
            
            <div className="flex h-full flex-col">
                {/* Top Bar */}
                <div className="border-b bg-background p-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <Link href="/templates">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Back
                                </Button>
                            </Link>
                            <div>
                                <h1 className="text-lg font-semibold">{typeName}</h1>
                                <p className="text-sm text-muted-foreground">{business.name}</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            {/* Undo/Redo buttons */}
                            <div className="flex items-center border rounded-md">
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    onClick={undo} 
                                    disabled={!canUndo}
                                    className="rounded-r-none border-r"
                                    title="Undo (Ctrl+Z)"
                                >
                                    <Undo2 className="h-4 w-4" />
                                </Button>
                                <Button 
                                    variant="ghost" 
                                    size="sm" 
                                    onClick={redo} 
                                    disabled={!canRedo}
                                    className="rounded-l-none"
                                    title="Redo (Ctrl+Shift+Z)"
                                >
                                    <Redo2 className="h-4 w-4" />
                                </Button>
                            </div>
                            <Select value={currentPreset} onValueChange={handlePresetChange}>
                                <SelectTrigger className="w-[140px]">
                                    <SelectValue placeholder="Select preset" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(presets).map(([key, label]) => (
                                        <SelectItem key={key} value={key}>{label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="sm" onClick={handlePreview}>
                                <Eye className="mr-2 h-4 w-4" />
                                Preview
                            </Button>
                            {isCustomized && (
                                <Button variant="outline" size="sm" onClick={handleReset} disabled={isResetting}>
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Reset
                                </Button>
                            )}
                            <Button size="sm" onClick={handleSave} disabled={isSaving}>
                                <Save className="mr-2 h-4 w-4" />
                                {isSaving ? 'Saving...' : 'Save'}
                            </Button>
                        </div>
                    </div>
                    {flash?.success && (
                        <div className="mt-3 rounded-md bg-green-50 p-3 text-sm text-green-700 dark:bg-green-950 dark:text-green-300">
                            {flash.success}
                        </div>
                    )}
                    {flash?.error && (
                        <div className="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
                            {flash.error}
                        </div>
                    )}
                </div>

                {/* Main Content */}
                <div className="flex flex-1 overflow-hidden">
                    {/* Left Panel - Block Types */}
                    <div className="w-64 border-r bg-muted/30 p-4 overflow-y-auto">
                        <h3 className="font-medium mb-3 text-sm text-muted-foreground uppercase tracking-wide">Blocks</h3>
                        <div className="space-y-2">
                            {blockTypes.map(({ type, label, icon: Icon, description }) => (
                                <div
                                    key={type}
                                    draggable
                                    onDragStart={(e) => handleDragStart(e, type)}
                                    className="flex items-center gap-3 p-3 bg-background rounded-lg border cursor-grab hover:border-primary hover:bg-primary/5 transition-colors"
                                >
                                    <div className="flex h-8 w-8 items-center justify-center rounded bg-primary/10">
                                        <Icon className="h-4 w-4 text-primary" />
                                    </div>
                                    <div>
                                        <div className="text-sm font-medium">{label}</div>
                                        <div className="text-xs text-muted-foreground">{description}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="mt-4 text-xs text-muted-foreground">
                            Drag blocks to the canvas to add them to your template.
                        </p>
                    </div>

                    {/* Center - Canvas */}
                    <div className="flex-1 overflow-y-auto p-6 bg-muted/10">
                        <div className="mx-auto max-w-2xl">
                            <p className="text-xs text-muted-foreground text-center mb-3">
                                Email preview (emails render on white backgrounds)
                            </p>
                            <Card className="shadow-lg">
                                <CardContent className="p-0">
                                    {/* Email canvas always white - emails render on light backgrounds */}
                                    <div
                                        className="min-h-[400px] bg-white rounded-lg"
                                        onDragOver={(e) => {
                                            e.preventDefault();
                                            e.dataTransfer.dropEffect = 'copy';
                                        }}
                                        onDrop={handleCanvasDrop}
                                    >
                                        {blocks.length === 0 ? (
                                            <div className="flex flex-col items-center justify-center h-[400px] text-gray-400">
                                                <Plus className="h-12 w-12 mb-4 text-gray-300" />
                                                <p className="text-lg font-medium text-gray-500">Drag blocks here</p>
                                                <p className="text-sm text-gray-400">Start building your template</p>
                                            </div>
                                        ) : (
                                            blocks.map((block, index) => (
                                                <div key={block.id}>
                                                    {/* Drop zone indicator */}
                                                    <div
                                                        className={`h-1 transition-all ${dragOverIndex === index ? 'bg-primary' : 'bg-transparent'}`}
                                                        onDragOver={(e) => handleDragOver(e, index)}
                                                        onDragLeave={handleDragLeave}
                                                        onDrop={(e) => handleDrop(e, index)}
                                                    />
                                                    
                                                    {/* Block */}
                                                    <div
                                                        className={`relative group cursor-pointer transition-all ${
                                                            selectedBlockId === block.id 
                                                                ? 'ring-2 ring-primary ring-offset-2' 
                                                                : 'hover:ring-1 hover:ring-muted-foreground/30'
                                                        }`}
                                                        onClick={() => setSelectedBlockId(block.id)}
                                                    >
                                                        {/* Block controls */}
                                                        <div className="absolute -left-10 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col gap-1">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6"
                                                                onClick={(e) => { e.stopPropagation(); moveBlock(index, 'up'); }}
                                                                disabled={index === 0}
                                                            >
                                                                <ChevronUp className="h-4 w-4" />
                                                            </Button>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6"
                                                                onClick={(e) => { e.stopPropagation(); moveBlock(index, 'down'); }}
                                                                disabled={index === blocks.length - 1}
                                                            >
                                                                <ChevronDown className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                        
                                                        <div className="absolute -right-10 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-6 w-6 text-destructive hover:text-destructive"
                                                                onClick={(e) => { e.stopPropagation(); deleteBlock(block.id); }}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                        
                                                        {renderBlockPreview(block)}
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                        {/* Final drop zone */}
                                        {blocks.length > 0 && (
                                            <div
                                                className={`h-8 transition-all ${dragOverIndex === blocks.length ? 'bg-primary/20' : 'bg-transparent'}`}
                                                onDragOver={(e) => handleDragOver(e, blocks.length)}
                                                onDragLeave={handleDragLeave}
                                                onDrop={(e) => handleDrop(e, blocks.length)}
                                            />
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Right Panel - Properties */}
                    <div className="w-80 border-l bg-muted/30 p-4 overflow-y-auto">
                        {selectedBlock ? (
                            <BlockPropertiesPanel
                                block={selectedBlock}
                                onUpdate={(property, value) => updateBlockProperty(selectedBlock.id, property, value)}
                            />
                        ) : (
                            <div className="flex flex-col items-center justify-center h-full text-muted-foreground">
                                <GripVertical className="h-8 w-8 mb-2" />
                                <p className="text-sm">Select a block to edit its properties</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface BlockPropertiesPanelProps {
    block: Block;
    onUpdate: (property: string, value: unknown) => void;
}

function BlockPropertiesPanel({ block, onUpdate }: BlockPropertiesPanelProps) {
    const blockType = blockTypes.find(b => b.type === block.type);
    const Icon = blockType?.icon || Type;

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-3 pb-3 border-b">
                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10">
                    <Icon className="h-5 w-5 text-primary" />
                </div>
                <div>
                    <h3 className="font-medium">{blockType?.label || 'Block'}</h3>
                    <p className="text-xs text-muted-foreground">Edit properties</p>
                </div>
            </div>

            {block.type === 'header' && (
                <>
                    <div className="space-y-2">
                        <Label>Business Name</Label>
                        <Input
                            value={block.properties.businessName as string}
                            onChange={(e) => onUpdate('businessName', e.target.value)}
                            placeholder="{{business_name}}"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Logo URL</Label>
                        <Input
                            value={block.properties.logoUrl as string}
                            onChange={(e) => onUpdate('logoUrl', e.target.value)}
                            placeholder="{{business_logo}}"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Background Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.backgroundColor as string}
                                onChange={(e) => onUpdate('backgroundColor', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.backgroundColor as string}
                                onChange={(e) => onUpdate('backgroundColor', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Text Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.textColor as string}
                                onChange={(e) => onUpdate('textColor', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.textColor as string}
                                onChange={(e) => onUpdate('textColor', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                </>
            )}

            {block.type === 'text' && (
                <>
                    <div className="space-y-2">
                        <Label>Content (HTML)</Label>
                        <Textarea
                            value={block.properties.content as string}
                            onChange={(e) => onUpdate('content', e.target.value)}
                            rows={4}
                            placeholder="<p>Your text here...</p>"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Font Size</Label>
                        <Select 
                            value={block.properties.fontSize as string} 
                            onValueChange={(v) => onUpdate('fontSize', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="12px">Small (12px)</SelectItem>
                                <SelectItem value="14px">Medium (14px)</SelectItem>
                                <SelectItem value="16px">Normal (16px)</SelectItem>
                                <SelectItem value="20px">Large (20px)</SelectItem>
                                <SelectItem value="24px">X-Large (24px)</SelectItem>
                                <SelectItem value="28px">Heading (28px)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Text Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.color as string}
                                onChange={(e) => onUpdate('color', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.color as string}
                                onChange={(e) => onUpdate('color', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Alignment</Label>
                        <Select 
                            value={block.properties.alignment as string} 
                            onValueChange={(v) => onUpdate('alignment', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="left">Left</SelectItem>
                                <SelectItem value="center">Center</SelectItem>
                                <SelectItem value="right">Right</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </>
            )}

            {block.type === 'button' && (
                <>
                    <div className="space-y-2">
                        <Label>Button Label</Label>
                        <Input
                            value={block.properties.label as string}
                            onChange={(e) => onUpdate('label', e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>URL</Label>
                        <Input
                            value={block.properties.url as string}
                            onChange={(e) => onUpdate('url', e.target.value)}
                            placeholder="{{payment_url}}"
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Background Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.backgroundColor as string}
                                onChange={(e) => onUpdate('backgroundColor', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.backgroundColor as string}
                                onChange={(e) => onUpdate('backgroundColor', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Text Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.textColor as string}
                                onChange={(e) => onUpdate('textColor', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.textColor as string}
                                onChange={(e) => onUpdate('textColor', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                </>
            )}

            {block.type === 'divider' && (
                <>
                    <div className="space-y-2">
                        <Label>Height</Label>
                        <Select 
                            value={block.properties.height as string} 
                            onValueChange={(v) => onUpdate('height', v)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="1px">Thin (1px)</SelectItem>
                                <SelectItem value="2px">Medium (2px)</SelectItem>
                                <SelectItem value="4px">Thick (4px)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.color as string}
                                onChange={(e) => onUpdate('color', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.color as string}
                                onChange={(e) => onUpdate('color', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                </>
            )}

            {block.type === 'table' && (
                <TablePropertiesEditor
                    rows={block.properties.rows as Array<{ label: string; value: string }>}
                    onRowsChange={(rows) => onUpdate('rows', rows)}
                    backgroundColor={block.properties.backgroundColor as string}
                    borderColor={block.properties.borderColor as string}
                    onBackgroundColorChange={(color) => onUpdate('backgroundColor', color)}
                    onBorderColorChange={(color) => onUpdate('borderColor', color)}
                />
            )}

            {block.type === 'footer' && (
                <>
                    <div className="space-y-2">
                        <Label>Footer Text (HTML)</Label>
                        <Textarea
                            value={block.properties.text as string}
                            onChange={(e) => onUpdate('text', e.target.value)}
                            rows={3}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Text Color</Label>
                        <div className="flex gap-2">
                            <Input
                                type="color"
                                value={block.properties.color as string}
                                onChange={(e) => onUpdate('color', e.target.value)}
                                className="w-12 h-9 p-1"
                            />
                            <Input
                                value={block.properties.color as string}
                                onChange={(e) => onUpdate('color', e.target.value)}
                                className="flex-1"
                            />
                        </div>
                    </div>
                </>
            )}

            <div className="pt-4 border-t">
                <p className="text-xs text-muted-foreground">
                    Use placeholders like <code className="bg-muted px-1 rounded">{'{{business_name}}'}</code> for dynamic content.
                </p>
            </div>
        </div>
    );
}

interface TablePropertiesEditorProps {
    rows: Array<{ label: string; value: string }>;
    onRowsChange: (rows: Array<{ label: string; value: string }>) => void;
    backgroundColor: string;
    borderColor: string;
    onBackgroundColorChange: (color: string) => void;
    onBorderColorChange: (color: string) => void;
}

function TablePropertiesEditor({ 
    rows, 
    onRowsChange, 
    backgroundColor, 
    borderColor,
    onBackgroundColorChange,
    onBorderColorChange
}: TablePropertiesEditorProps) {
    const addRow = () => {
        onRowsChange([...rows, { label: 'Label', value: 'Value' }]);
    };

    const removeRow = (index: number) => {
        onRowsChange(rows.filter((_, i) => i !== index));
    };

    const updateRow = (index: number, field: 'label' | 'value', value: string) => {
        const newRows = [...rows];
        newRows[index] = { ...newRows[index], [field]: value };
        onRowsChange(newRows);
    };

    return (
        <>
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <Label>Table Rows</Label>
                    <Button variant="outline" size="sm" onClick={addRow}>
                        <Plus className="h-3 w-3 mr-1" />
                        Add Row
                    </Button>
                </div>
                <div className="space-y-2">
                    {rows.map((row, index) => (
                        <div key={index} className="flex gap-2 items-center">
                            <Input
                                value={row.label}
                                onChange={(e) => updateRow(index, 'label', e.target.value)}
                                placeholder="Label"
                                className="flex-1"
                            />
                            <Input
                                value={row.value}
                                onChange={(e) => updateRow(index, 'value', e.target.value)}
                                placeholder="Value"
                                className="flex-1"
                            />
                            <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => removeRow(index)}
                                disabled={rows.length <= 1}
                                className="h-8 w-8"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                    ))}
                </div>
            </div>
            <div className="space-y-2">
                <Label>Background Color</Label>
                <div className="flex gap-2">
                    <Input
                        type="color"
                        value={backgroundColor}
                        onChange={(e) => onBackgroundColorChange(e.target.value)}
                        className="w-12 h-9 p-1"
                    />
                    <Input
                        value={backgroundColor}
                        onChange={(e) => onBackgroundColorChange(e.target.value)}
                        className="flex-1"
                    />
                </div>
            </div>
            <div className="space-y-2">
                <Label>Border Color</Label>
                <div className="flex gap-2">
                    <Input
                        type="color"
                        value={borderColor}
                        onChange={(e) => onBorderColorChange(e.target.value)}
                        className="w-12 h-9 p-1"
                    />
                    <Input
                        value={borderColor}
                        onChange={(e) => onBorderColorChange(e.target.value)}
                        className="flex-1"
                    />
                </div>
            </div>
        </>
    );
}
