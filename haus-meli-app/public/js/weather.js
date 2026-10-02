document.addEventListener('DOMContentLoaded', function () {
    const icons = {
        sunny: '☀️',
        partly_cloudy_day: '⛅',
        bedtime: '🌙',
        partly_cloudy_night: '☁️',
        cloud: '☁️',
        rainy: '🌧️',
        weather_snowy: '🌨️',
        thunderstorm: '⛈️',
        foggy: '🌫️'
    };

    function paint(data) {
        const header = document.getElementById('header-weather');
        if (!header || !data || !data.ok || data.temp === null || data.temp === undefined) return;
        const icon = document.getElementById('hw-icon');
        const temp = document.getElementById('hw-temp');
        if (icon) icon.textContent = icons[data.icon] || '⛅';
        if (temp) temp.textContent = Math.round(data.temp) + '°C';
        header.style.display = 'flex';
    }

    function load() {
        fetch('/api/weather', { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(paint)
            .catch(function () {});
    }

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
            load();
            window.dispatchEvent(new CustomEvent('haus-weather-hour'));
            schedule();
        }, msUntilNextHour());
    }

    load();
    schedule();
});
