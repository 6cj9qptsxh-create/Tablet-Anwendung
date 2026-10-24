document.addEventListener('DOMContentLoaded', function () {
    function msUntilNextHour() {
        const parts = new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Europe/Vienna',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23'
        }).formatToParts(new Date());
        const pick = function (type) {
            const part = parts.find(function (item) { return item.type === type; });
            return part ? parseInt(part.value, 10) : 0;
        };
        const intoHour = pick('minute') * 60 + pick('second');
        return (3600 - intoHour) * 1000 + 4000;
    }

    function schedule() {
        setTimeout(function () {
            window.dispatchEvent(new CustomEvent('haus-weather-hour'));
            schedule();
        }, msUntilNextHour());
    }

    schedule();
});
