'use script';

class Clock {
    defaultOptions = {
        dateId: 'date',
        monthId: 'month',
        yearId: 'year',
        hoursId: 'hours',
        minutesId: 'minutes',
        secondsId: 'seconds',
        timeZoneOffset: null,
        monthNames: [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December'
        ]
    };

    constructor(options) {
        // options = {
        //     dateId: 'date',
        //     monthId: 'month',
        //     yearId: 'year',
        //     hoursId: 'hours',
        //     minutesId: 'minutes',
        //     secondsId: 'seconds',
        //     timeZoneOffset: +4.00,
        //     monthNames: [
        //         'January',
        //         'February',
        //         'March',
        //         'April',
        //         'May',
        //         'June',
        //         'July',
        //         'August',
        //         'September',
        //         'October',
        //         'November',
        //         'December'
        //     ]
        // }
        if (typeof options === 'undefined') {
            options = this.defaultOptions;
        }

        const keys = Object.keys(this.defaultOptions);
        for (const key of keys) {
            if (typeof options[key] === 'undefined') {
                options[key] = this.defaultOptions[key];
            }
        }

        this.monthNames = options.monthNames;

        // get all clock elements
        this.dateElement = document.getElementById(options.dateId);
        this.monthElement = document.getElementById(options.monthId);
        this.yearElement = document.getElementById(options.yearId);
        this.hoursElement = document.getElementById(options.hoursId);
        this.minutesElement = document.getElementById(options.minutesId);
        this.secondsElement = document.getElementById(options.secondsId);

        // calculate timezone offset
        if (options.timeZoneOffset != null) {
            let d = new Date();
            let tzDifference = options.timeZoneOffset * 60 + d.getTimezoneOffset();
            this.offset = tzDifference * 60 * 1000;
        }
    }

    render() {
        let date = new Date();
        if (typeof this.offset !== 'undefined') {
            date = new Date(new Date().getTime() + this.offset);
        }

        if (this.secondsElement != null) {
            let seconds = date.getSeconds();
            if (seconds < 10) seconds = '0' + seconds;
            this.secondsElement.innerText = '' + seconds;
        }

        if (this.hoursElement != null) {
            let hours = date.getHours();
            if (hours < 10) hours = '0' + hours;

            if (this.hoursElement.innerText !== '' + hours) {
                this.hoursElement.innerText = '' + hours;
            }
        }

        if (this.minutesElement != null) {
            let minutes = date.getMinutes();
            if (minutes < 10) minutes = '0' + minutes;

            if (this.minutesElement.innerText !== '' + minutes) {
                this.minutesElement.innerText = '' + minutes;
            }
        }

        if (this.dateElement != null) {
            let day = date.getDate();

            if (this.dateElement.innerText !== '' + day) {
                this.dateElement.innerText = '' + day;
            }
        }

        if (this.monthElement != null) {
            let month_index = date.getMonth();
            if (this.monthElement.innerText !== this.monthNames[month_index]) {
                this.monthElement.innerText = this.monthNames[month_index];
            }
        }

        if (this.yearElement != null) {
            let year = date.getFullYear();
            if (this.yearElement.innerText !== '' + year) {
                this.yearElement.innerText = '' + year;
            }
        }
    }

    stop() {
        clearInterval(this.timer);
    }

    start() {
        this.render();
        this.timer = setInterval(() => this.render(), 1000)
    }
}
