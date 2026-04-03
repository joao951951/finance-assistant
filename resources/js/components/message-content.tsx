export function MessageContent({ content }: { content: string }) {
    const parts = content.split(/(\*\*[^*]+\*\*)/g);

    return (
        <p className="text-sm leading-relaxed whitespace-pre-wrap">
            {parts.map((part, i) =>
                part.startsWith('**') && part.endsWith('**')
                    ? <strong key={i}>{part.slice(2, -2)}</strong>
                    : part
            )}
        </p>
    );
}
